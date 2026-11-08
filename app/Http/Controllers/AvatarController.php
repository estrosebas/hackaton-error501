<?php

namespace App\Http\Controllers;

use App\Services\FaceIdentityMatch;
use App\Services\FacePhotoCheck;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    public function show(Request $request)
    {
        $avatar = $request->user()->getFirstMedia('avatar');
        if (! $avatar) {
            return app(FacePhotoController::class)->show($request);
        }

        return response()->file($avatar->getPath(), ['Cache-Control' => 'private, no-store']);
    }

    public function update(Request $request, FacePhotoCheck $checker)
    {
        $data = $request->validate(['foto' => ['required', 'string', 'max:5600000']]);
        $bytes = $checker->decode($data['foto']);
        $analysis = $checker->analyze($bytes, extract: true);
        app(FaceIdentityMatch::class)->validate($request->user(), $analysis);
        $mime = getimagesizefromstring($bytes)['mime'];
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $request->user()->addMediaFromString($bytes)->usingFileName('avatar.'.$extension)->toMediaCollection('avatar', 'local');

        return response()->json(['message' => 'Foto de perfil actualizada.']);
    }
}
