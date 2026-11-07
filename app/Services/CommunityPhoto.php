<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CommunityPhoto
{
    public function store(UploadedFile $photo): string
    {
        // Re-encode shared photos so EXIF metadata (including GPS) is never served.
        $image = @imagecreatefromstring($photo->getContent());
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'No pudimos abrir la foto. Elige otro archivo.']);
        }
        $orientation = $photo->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')
            ? (int) (@exif_read_data($photo->getPathname())['Orientation'] ?? 1)
            : 1;
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }
        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };
        if ($angle !== 0) {
            $image = imagerotate($image, $angle, 0);
            abort_unless($image, 500, 'No pudimos orientar la foto.');
        }
        imagepalettetotruecolor($image);
        imagesavealpha($image, true);

        ob_start();
        try {
            $encoded = imagewebp($image, null, 85);
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        abort_unless($encoded && $contents, 500, 'No pudimos preparar la foto.');

        $path = 'community-photos/'.Str::uuid().'.webp';
        abort_unless(Storage::disk('local')->put($path, $contents), 500, 'No pudimos guardar la foto.');

        return $path;
    }
}
