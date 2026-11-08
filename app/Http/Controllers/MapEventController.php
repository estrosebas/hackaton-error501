<?php

namespace App\Http\Controllers;

use App\Models\MapEvent;
use App\Services\CommunityPhoto;
use App\Services\EmpathyRewards;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MapEventController extends Controller
{
    private function visible(Request $request, MapEvent $event): void
    {
        abort_unless($request->user()->ubigeo && $request->user()->ubigeo === $event->ubigeo, 404);
        abort_if(DB::table('neighbor_blocks')->where(function ($q) use ($request, $event) {
            $q->where(fn ($q) => $q->where('user_id', $request->user()->id)->where('blocked_user_id', $event->user_id))
                ->orWhere(fn ($q) => $q->where('user_id', $event->user_id)->where('blocked_user_id', $request->user()->id));
        })->exists(), 404);
    }

    private function present(MapEvent $event, int $viewer, bool $preloaded = false, ?object $attendance = null, ?int $participants = null): array
    {
        if (! $preloaded) {
            $attendance = DB::table('map_event_attendances')->where('map_event_id', $event->id)->where('user_id', $viewer)->first();
        }

        return [
            'id' => $event->id, 'title' => $event->title, 'description' => $event->description,
            'category' => $event->category, 'type' => $event->type, 'place' => $event->place, 'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(), 'latitude' => (float) $event->latitude, 'longitude' => (float) $event->longitude,
            'has_cost' => $event->has_cost, 'points' => $event->points, 'frame' => $event->frame,
            'has_photo' => (bool) $event->photo_path, 'author' => ['id' => $event->user_id, 'name' => $event->author->name],
            'is_owner' => $event->user_id === $viewer, 'cancelled' => (bool) $event->cancelled_at,
            'joined' => (bool) $attendance, 'attended' => (bool) $attendance?->processed_at,
            'participants' => $participants ?? DB::table('map_event_attendances')->where('map_event_id', $event->id)->count(),
        ];
    }

    private function located()
    {
        return MapEvent::select('map_events.*')->selectRaw('ST_Y(location::geometry) AS latitude, ST_X(location::geometry) AS longitude')->with('author:id,name');
    }

    public function catalog(Request $request)
    {
        $points = (int) DB::table('empathy_rewards')->where('user_id', $request->user()->id)->sum('points');

        return response()->json(['points' => $points,
            'categories' => collect(config('map-events.categories'))->map(fn ($item, $id) => ['id' => $id, ...$item])->values(),
            'frames' => collect(config('map-events.frames'))->map(fn ($item, $id) => ['id' => $id, ...$item, 'unlocked' => $points >= $item['points']])->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request)
    {
        $data = $request->validate(['latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'], 'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'], 'category' => ['nullable', Rule::in(array_keys(config('map-events.categories')))]]);
        $actor = $request->user();
        $query = $this->located()->where('ubigeo', $actor->ubigeo)->whereNotNull('ubigeo')->where('ends_at', '>=', now())->whereNull('cancelled_at');
        $blocked = DB::table('neighbor_blocks')->where('user_id', $actor->id)->pluck('blocked_user_id')
            ->merge(DB::table('neighbor_blocks')->where('blocked_user_id', $actor->id)->pluck('user_id'));
        $query->whereNotIn('user_id', $blocked);
        if (isset($data['latitude'], $data['longitude'])) {
            $query->whereRaw('ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?),4326)::geography, 30000)', [$data['longitude'], $data['latitude']]);
        }
        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }

        $events = $query->orderBy('starts_at')->limit(201)->get();
        $ids = $events->take(200)->pluck('id');
        $attendances = DB::table('map_event_attendances')->whereIn('map_event_id', $ids)->where('user_id', $actor->id)->get()->keyBy('map_event_id');
        $counts = DB::table('map_event_attendances')->whereIn('map_event_id', $ids)->groupBy('map_event_id')->selectRaw('map_event_id, count(*) AS total')->pluck('total', 'map_event_id');

        return response()->json(['data' => $events->take(200)->map(fn ($event) => $this->present($event, $actor->id, true, $attendances->get($event->id), (int) $counts->get($event->id, 0)))->values(), 'has_more' => $events->count() > 200])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, CommunityPhoto $photos)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'], 'description' => ['required', 'string', 'max:2000'],
            'place' => ['required', 'string', 'max:180'], 'category' => ['required', Rule::in(array_keys(config('map-events.categories')))],
            'type' => ['nullable', 'string', Rule::in(config('map-events.categories.'.(is_string($request->input('category')) ? $request->input('category') : '').'.types', []))],
            'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'],
            'starts_at' => ['required', 'date', 'after_or_equal:-5 minutes', 'before:+1 year'],
            'ends_at' => ['required', 'date', 'after:starts_at'], 'has_cost' => ['required', 'boolean'],
            'frame' => ['nullable', Rule::in(array_keys(config('map-events.frames')))],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2400,max_height=2400'],
        ]);
        if (Carbon::parse($data['ends_at'])->diffInHours(Carbon::parse($data['starts_at']), true) > 48) {
            throw ValidationException::withMessages(['ends_at' => 'El evento puede durar hasta 48 horas.']);
        }
        $actor = $request->user();
        abort_unless($actor->ubigeo, 422, 'Completa tu distrito antes de crear un evento.');
        $points = (int) DB::table('empathy_rewards')->where('user_id', $actor->id)->sum('points');
        if (! empty($data['frame']) && $points < config('map-events.frames.'.$data['frame'].'.points')) {
            throw ValidationException::withMessages(['frame' => 'Todavía no desbloqueaste este marco.']);
        }
        $path = $request->hasFile('photo') ? $photos->store($request->file('photo')) : null;
        try {
            $event = DB::transaction(function () use ($actor, $data, $path) {
                $attributes = collect($data)->except(['photo', 'latitude', 'longitude'])->all();
                $id = DB::table('map_events')->insertGetId([
                    ...$attributes, 'user_id' => $actor->id, 'ubigeo' => $actor->ubigeo,
                    'points' => $data['category'] === 'support' && $data['has_cost'] ? 0 : config('map-events.categories.'.$data['category'].'.points'),
                    'photo_path' => $path, 'created_at' => now(), 'updated_at' => now(),
                    'location' => DB::raw('ST_SetSRID(ST_MakePoint('.(float) $data['longitude'].','.(float) $data['latitude'].'),4326)::geography'),
                ]);

                return $this->located()->findOrFail($id);
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return response()->json(['data' => $this->present($event, $actor->id)], 201);
    }

    public function join(Request $request, MapEvent $event)
    {
        $this->visible($request, $event);
        DB::transaction(function () use ($request, $event) {
            $locked = MapEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->cancelled_at || $locked->ends_at->isPast(), 422, 'Este evento ya terminó o fue cancelado.');
            DB::table('map_event_attendances')->insertOrIgnore(['map_event_id' => $event->id, 'user_id' => $request->user()->id, 'joined_at' => now()]);
        });

        return response()->json(['data' => $this->present($this->located()->findOrFail($event->id), $request->user()->id)]);
    }

    public function leave(Request $request, MapEvent $event)
    {
        $this->visible($request, $event);
        DB::transaction(function () use ($request, $event) {
            $attendance = DB::table('map_event_attendances')->where('map_event_id', $event->id)->where('user_id', $request->user()->id)->lockForUpdate()->first();
            abort_if($attendance?->verified_at, 422, 'Tu asistencia ya fue registrada.');
            if ($attendance) {
                DB::table('map_event_attendances')->where('id', $attendance->id)->delete();
            }
        });

        return response()->noContent();
    }

    public function checkIn(Request $request, MapEvent $event, EmpathyRewards $rewards)
    {
        $this->visible($request, $event);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'between:0,100'],
            'captured_at' => ['required', 'date', 'after:-2 minutes', 'before_or_equal:now'],
        ]);
        $id = DB::transaction(function () use ($request, $event, $data) {
            $locked = MapEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $attendance = DB::table('map_event_attendances')->where('map_event_id', $event->id)->where('user_id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($attendance, 422, 'Primero inscríbete en el evento.');
            if ($attendance->processed_at) {
                return $attendance->id;
            }
            $captured = Carbon::parse($data['captured_at']);
            abort_unless(! $locked->cancelled_at && now()->betweenIncluded($locked->starts_at, $locked->ends_at) && $captured->betweenIncluded($locked->starts_at, $locked->ends_at) && $captured->gte(Carbon::parse($attendance->joined_at)), 422, 'La asistencia se registra durante el horario del evento.');
            $distance = DB::selectOne('SELECT ST_Distance(location, ST_SetSRID(ST_MakePoint(?,?),4326)::geography) AS meters, ST_DWithin(location, ST_SetSRID(ST_MakePoint(?,?),4326)::geography, 100) AS nearby FROM map_events WHERE id = ?', [$data['longitude'], $data['latitude'], $data['longitude'], $data['latitude'], $event->id]);
            abort_unless($distance->nearby, 422, 'Debes estar a 100 metros o menos del evento.');
            DB::table('map_event_attendances')->where('id', $attendance->id)->whereNull('verified_at')->update(['verified_at' => $captured, 'distance_meters' => round($distance->meters, 2)]);

            return $attendance->id;
        });
        $rewards->process($id);

        return response()->json(['data' => $this->present($this->located()->findOrFail($event->id), $request->user()->id), 'message' => 'Asistencia registrada.']);
    }

    public function cancel(Request $request, MapEvent $event)
    {
        $this->visible($request, $event);
        abort_unless($event->user_id === $request->user()->id, 403);
        DB::transaction(fn () => MapEvent::whereKey($event->id)->lockForUpdate()->firstOrFail()->update(['cancelled_at' => now()]));

        return response()->noContent();
    }

    public function photo(Request $request, MapEvent $event)
    {
        $this->visible($request, $event);
        abort_unless($event->photo_path && Storage::disk('local')->exists($event->photo_path), 404);

        return response()->file(Storage::disk('local')->path($event->photo_path), ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
