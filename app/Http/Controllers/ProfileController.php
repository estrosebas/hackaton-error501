<?php

namespace App\Http\Controllers;

use App\Http\Requests\NeighborProfileRules;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json(['user' => $user, 'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'face_enrolled' => $user->faceProfile()->exists(),
            'expires_at' => $user->currentAccessToken()?->expires_at?->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function location(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'between:0,99999999'],
            'location_captured_at' => ['required', 'date', 'before_or_equal:now', 'after:-30 minutes'],
        ]);
        $request->user()->update($data);

        return response()->json(['message' => 'Ubicación actualizada.'])->header('Cache-Control', 'no-store');
    }

    public function updatePresentation(Request $request)
    {
        $changes = $request->validate(NeighborProfileRules::rules(required: true))['neighbor_profile'];
        if (array_key_exists('can_help', $changes)) {
            $changes['can_help'] = (bool) $changes['can_help'];
        }
        $userId = $request->user()->getKey();

        $profile = DB::transaction(function () use ($userId, $changes) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $profile = array_replace($user->neighbor_profile ?? [], $changes);
            $user->update(['neighbor_profile' => $profile]);

            return $profile;
        });

        return response()->json(['data' => $profile])->header('Cache-Control', 'no-store');
    }
}
