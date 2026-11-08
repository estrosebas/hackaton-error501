<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NeighborConnections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NeighborEndorsementController extends Controller
{
    public function show(Request $request, User $neighbor, NeighborConnections $connections)
    {
        $connections->neighbor($request->user(), $neighbor);
        abort_if($connections->blocked($request->user()->id, $neighbor->id), 404);

        return response()->json(['data' => $this->stats($request, $neighbor)])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, User $neighbor, NeighborConnections $connections)
    {
        $data = $request->validate(['selected' => ['required', 'boolean']]);
        abort_if($neighbor->id === $request->user()->id, 422, 'No puedes darte visto bueno a ti mismo.');
        DB::transaction(function () use ($request, $neighbor, $connections, $data) {
            $connections->lockUsers([$request->user()->id, $neighbor->id]);
            $actor = $request->user()->fresh();
            $target = $neighbor->fresh();
            $connections->neighbor($actor, $target);
            abort_if($connections->blocked($actor->id, $target->id), 404);
            $key = ['user_id' => $actor->id, 'neighbor_id' => $target->id];
            if ($data['selected']) {
                DB::table('neighbor_endorsements')->insertOrIgnore([...$key, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('neighbor_endorsements')->where($key)->delete();
            }
        });

        return response()->json(['data' => $this->stats($request, $neighbor)])->header('Cache-Control', 'no-store');
    }

    private function stats(Request $request, User $neighbor): array
    {
        return ['neighbor_id' => $neighbor->id, 'count' => DB::table('neighbor_endorsements')->where('neighbor_id', $neighbor->id)->count(), 'selected' => DB::table('neighbor_endorsements')->where('neighbor_id', $neighbor->id)->where('user_id', $request->user()->id)->exists()];
    }
}
