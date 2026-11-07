<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json(['user' => $user, 'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'face_enrolled' => $user->faceProfile()->exists()])->header('Cache-Control', 'no-store');
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
}
