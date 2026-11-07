<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NeighborConnections;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class NeighborController extends Controller
{
    public function index(Request $request, NeighborConnections $connections)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        abort_unless($request->user()->ubigeo, 422, 'Completa tu distrito.');
        $items = User::where('ubigeo', $request->user()->ubigeo)->where('id', '!=', $request->user()->id)
            ->when($data['q'] ?? '', fn ($q, $term) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']))
            ->orderBy('name')->limit(50)->get();

        return response()->json(['data' => $items->map(fn ($user) => $connections->profile($user))]);
    }

    public function show(Request $request, User $neighbor, NeighborConnections $connections)
    {
        $connections->neighbor($request->user(), $neighbor);

        return response()->json(['data' => [...$connections->profile($neighbor),
            'profile' => Arr::only($neighbor->neighbor_profile ?? [], ['bio', 'skills', 'interests', 'availability', 'can_help']), 'joined' => $neighbor->created_at->format('Y-m'),
            'blocked_by_me' => DB::table('neighbor_blocks')->where('user_id', $request->user()->id)->where('blocked_user_id', $neighbor->id)->exists(),
            'can_message' => $neighbor->id !== $request->user()->id && ! $connections->blocked($request->user()->id, $neighbor->id),
        ]]);
    }

    public function blocks(Request $request, NeighborConnections $connections)
    {
        $items = User::whereIn('id', DB::table('neighbor_blocks')->select('blocked_user_id')->where('user_id', $request->user()->id))->orderBy('name')->get();

        return response()->json(['data' => $items->map(fn ($user) => $connections->profile($user))]);
    }

    public function block(Request $request, User $neighbor, NeighborConnections $connections)
    {
        abort_if($neighbor->id === $request->user()->id, 422, 'No puedes bloquearte a ti mismo.');
        $data = $request->validate(['blocked' => ['required', 'boolean']]);
        // Unblocking remains possible even if someone changes districts.
        if ($data['blocked']) {
            $known = DB::table('chat_members as a')->join('chat_members as b', 'a.room_id', '=', 'b.room_id')
                ->where('a.user_id', $request->user()->id)->where('b.user_id', $neighbor->id)->exists();
            abort_unless($known || ($request->user()->ubigeo && $request->user()->ubigeo === $neighbor->ubigeo), 404);
        }
        DB::transaction(function () use ($request, $neighbor, $connections, $data) {
            $connections->lockUsers([$request->user()->id, $neighbor->id]);
            $keys = ['user_id' => $request->user()->id, 'blocked_user_id' => $neighbor->id];
            if ($data['blocked']) {
                DB::table('neighbor_blocks')->insertOrIgnore($keys);
            } else {
                DB::table('neighbor_blocks')->where($keys)->delete();
            }
        });

        return response()->json(['blocked' => $data['blocked']]);
    }
}
