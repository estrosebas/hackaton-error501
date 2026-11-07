<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class FaceEnrollment
{
    public function save(User $user, string $bytes, array $analysis): void
    {
        $info = getimagesizefromstring($bytes);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime']];
        $path = 'faces/'.$user->id.'/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new \RuntimeException('No se pudo guardar la fotografía privada.');
        }
        $oldPath = null;
        try {
            DB::transaction(function () use ($user, $path, $analysis, &$oldPath) {
                // Serialize replacements so simultaneous requests cannot orphan photos.
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $oldPath = $user->faceProfile()->value('photo_path');
                $user->faceProfile()->updateOrCreate([], [
                    'photo_path' => $path, 'embedding' => $analysis['embedding'],
                    'model' => $analysis['model'], 'model_fingerprint' => $analysis['model_fingerprint'],
                    'quality_policy' => $analysis['quality_policy'], 'enrolled_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }
    }
}
