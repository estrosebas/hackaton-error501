<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Services\NeighborConnections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function __construct(private NeighborConnections $connections) {}

    private function member(Request $request, ChatRoom $room): void
    {
        abort_unless($room->members()->where('users.id', $request->user()->id)->exists(), 404);
    }

    private function present(ChatRoom $room, Request $request): array
    {
        $members = $room->members;
        $lastRead = (int) $members->firstWhere('id', $request->user()->id)->pivot->last_read_id;
        $last = $room->messages()->latest('id')->first();
        $others = $members->where('id', '!=', $request->user()->id);

        return ['id' => $room->id, 'kind' => $room->kind, 'name' => $room->kind === 'direct' ? $others->first()?->name : $room->name,
            'owner_id' => $room->owner_id, 'members' => $members->map(fn ($u) => [...$this->connections->profile($u),
                'blocked_by_me' => $this->connections->blockedBy($request->user()->id, $u->id),
            ])->values(),
            'can_send' => ! $others->contains(fn ($u) => $this->connections->blocked($request->user()->id, $u->id)),
            'last_message' => $last ? ['body' => $last->body, 'at' => $last->created_at->toISOString()] : null,
            'unread' => $room->messages()->where('id', '>', $lastRead)->where('user_id', '!=', $request->user()->id)->count(),
        ];
    }

    public function index(Request $request)
    {
        $items = ChatRoom::whereHas('members', fn ($q) => $q->where('users.id', $request->user()->id))->with('members')->orderByDesc('updated_at')->orderByDesc('id')->paginate(30);
        $this->connections->primeBlocks($items->getCollection()->flatMap(fn ($r) => $r->members->pluck('id'))->unique()->all());

        return response()->json(['data' => $items->getCollection()->map(fn ($room) => $this->present($room, $request)), 'next_page' => $items->hasMorePages() ? $items->currentPage() + 1 : null]);
    }

    public function show(Request $request, ChatRoom $room)
    {
        $this->member($request, $room);
        $room->load('members');
        $this->connections->primeBlocks($room->members->pluck('id')->all());

        return response()->json(['data' => $this->present($room, $request)]);
    }

    public function direct(Request $request, User $neighbor)
    {
        abort_if($neighbor->id === $request->user()->id, 422, 'Elige a otro vecino.');
        $this->connections->neighbor($request->user(), $neighbor);
        $room = DB::transaction(function () use ($request, $neighbor) {
            $ids = [$request->user()->id, $neighbor->id];
            sort($ids);
            $this->connections->lockUsers($ids);
            $this->connections->primeBlocks($ids);
            abort_if($this->connections->blocked(...$ids), 403, 'Esta conversación está bloqueada.');
            $room = ChatRoom::firstOrCreate(['direct_key' => implode(':', $ids)], ['owner_id' => $request->user()->id, 'kind' => 'direct']);
            $room->members()->syncWithoutDetaching($ids);

            return $room;
        });

        return response()->json(['data' => $this->present($room, $request)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'members' => ['required', 'array', 'min:1', 'max:29'], 'members.*' => ['required', 'integer', 'distinct', 'exists:users,id']]);
        $room = DB::transaction(function () use ($request, $data) {
            $ids = array_unique([...$data['members'], $request->user()->id]);
            abort_if(count($ids) < 2, 422, 'Elige al menos a otro vecino.');
            $this->connections->lockUsers($ids);
            $members = User::whereIn('id', $ids)->get();
            $this->connections->primeBlocks($ids);
            foreach ($members as $user) {
                $this->connections->neighbor($request->user(), $user);
                foreach ($members as $other) {
                    abort_if($this->connections->blocked($user->id, $other->id), 403, 'No se puede reunir a participantes con un bloqueo activo.');
                }
            }
            $room = ChatRoom::create(['kind' => 'group', 'owner_id' => $request->user()->id, 'name' => trim($data['name'])]);
            $room->members()->attach($ids);

            return $room;
        });

        return response()->json(['data' => $this->present($room, $request)], 201);
    }

    public function invite(Request $request, ChatRoom $room)
    {
        $this->member($request, $room);
        abort_unless($room->kind === 'group' && $room->owner_id === $request->user()->id, 403);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        DB::transaction(function () use ($request, $room, $data) {
            $ids = [...$room->members()->pluck('users.id')->all(), (int) $data['user_id']];
            $this->connections->lockUsers($ids);
            $lockedRoom = ChatRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRoom->owner_id === $request->user()->id && $lockedRoom->kind === 'group', 403);
            $this->member($request, $room);
            $members = $room->members()->get();
            $this->connections->primeBlocks($ids);
            $target = User::findOrFail($data['user_id']);
            $this->connections->neighbor($request->user(), $target);
            abort_if($members->count() >= 30 && ! $members->contains('id', $target->id), 422, 'La sala admite hasta 30 vecinos.');
            foreach ($members as $member) {
                abort_if($this->connections->blocked($member->id, $target->id), 403, 'Existe un bloqueo entre participantes.');
            }
            $room->members()->syncWithoutDetaching([$target->id]);
            $room->touch();
        });

        return response()->json(['data' => $this->present($room->fresh(), $request)]);
    }

    public function leave(Request $request, ChatRoom $room)
    {
        $this->member($request, $room);
        abort_unless($room->kind === 'group', 422, 'Las conversaciones directas se pueden bloquear desde el perfil.');
        DB::transaction(function () use ($request, $room) {
            $this->connections->lockUsers($room->members()->pluck('users.id')->all());
            $locked = ChatRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $room->members()->detach($request->user()->id);
            if ($locked->owner_id === $request->user()->id) {
                $next = $room->members()->orderBy('users.id')->first();
                if ($next) {
                    $locked->update(['owner_id' => $next->id]);
                } else {
                    $locked->delete();
                }
            }
        });

        return response()->noContent();
    }

    private function message(ChatMessage $message, Request $request): array
    {
        return ['id' => $message->id, 'author' => $this->connections->profile($message->author), 'body' => $message->body,
            'at' => $message->created_at->toISOString(), 'owned' => $message->user_id === $request->user()->id];
    }

    public function messages(Request $request, ChatRoom $room)
    {
        $this->member($request, $room);
        $data = $request->validate(['before_id' => ['nullable', 'integer', 'min:1'], 'after_id' => ['nullable', 'integer', 'min:0']]);
        $query = $room->messages()->with('author');
        if (isset($data['after_id'])) {
            $items = $query->where('id', '>', $data['after_id'])->orderBy('id')->limit(50)->get();
        } else {
            $items = $query->when($data['before_id'] ?? null, fn ($q, $id) => $q->where('id', '<', $id))->orderByDesc('id')->limit(30)->get()->reverse()->values();
        }
        $before = $items->first()?->id;

        return response()->json(['data' => $items->map(fn ($m) => $this->message($m, $request)),
            'older_cursor' => $before && $room->messages()->where('id', '<', $before)->exists() ? $before : null]);
    }

    public function send(Request $request, ChatRoom $room)
    {
        $this->member($request, $room);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $message = DB::transaction(function () use ($request, $room, $data) {
            $ids = $room->members()->pluck('users.id')->all();
            $this->connections->lockUsers($ids);
            ChatRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $this->member($request, $room);
            $this->connections->primeBlocks($room->members()->pluck('users.id')->all());
            foreach ($room->members()->where('users.id', '!=', $request->user()->id)->pluck('users.id') as $id) {
                abort_if($this->connections->blocked($request->user()->id, $id), 403, 'No puedes enviar mensajes mientras exista un bloqueo con un participante.');
            }
            $message = $room->messages()->create(['user_id' => $request->user()->id, 'body' => trim($data['body'])]);
            $room->touch();
            $room->members()->updateExistingPivot($request->user()->id, ['last_read_id' => $message->id]);

            return $message;
        });

        return response()->json(['data' => $this->message($message->load('author'), $request)], 201);
    }

    public function read(Request $request, ChatRoom $room)
    {
        $this->member($request, $room);
        $data = $request->validate(['message_id' => ['required', 'integer', 'min:1']]);
        abort_unless($room->messages()->whereKey($data['message_id'])->exists(), 422);
        DB::table('chat_members')->where('room_id', $room->id)->where('user_id', $request->user()->id)
            ->update(['last_read_id' => DB::raw('GREATEST(last_read_id, '.(int) $data['message_id'].')')]);

        return response()->noContent();
    }
}
