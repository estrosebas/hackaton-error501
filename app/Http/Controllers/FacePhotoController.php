<?php

namespace App\Http\Controllers;

use App\Services\FaceEnrollment;
use App\Services\FaceIdentityMatch;
use App\Services\FacePhotoCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FacePhotoController extends Controller
{
    public function check(Request $request, FacePhotoCheck $checker)
    {
        $data = $request->validate(['foto' => ['required', 'string', 'max:5600000']]);
        $analysis = $checker->analyze($checker->decode($data['foto']));

        return response()->json([
            'accepted' => true, 'model' => $analysis['model'] ?? 'sface_yunet',
            'liveness' => 'not_evaluated',
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, FacePhotoCheck $checker, FaceEnrollment $enrollment)
    {
        $data = $request->validate([
            'foto' => ['required', 'string', 'max:5600000'],
            'biometric_consent' => ['required', 'accepted'],
        ]);
        $bytes = $checker->decode($data['foto']);
        $analysis = $checker->analyze($bytes, extract: true);
        app(FaceIdentityMatch::class)->validate($request->user(), $analysis);
        $enrollment->save($request->user(), $bytes, $analysis);

        return response()->json(['message' => 'Tu fotografía se actualizó.', 'enrolled_at' => now()->toIso8601String()]);
    }

    public function show(Request $request)
    {
        $profile = $request->user()->faceProfile;
        abort_unless($profile && Storage::disk('local')->exists($profile->photo_path), 404);

        return Storage::disk('local')->response($profile->photo_path, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
