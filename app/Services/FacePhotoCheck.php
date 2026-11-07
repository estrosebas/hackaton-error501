<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class FacePhotoCheck
{
    public function decode(string $photo): string
    {
        if (! preg_match('~^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$~D', $photo, $matches)) {
            throw ValidationException::withMessages(['foto' => 'La fotografía debe ser una imagen JPEG, PNG o WebP.']);
        }
        $bytes = base64_decode($matches[2], true);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (! $info || strlen($bytes) > 4 * 1024 * 1024 || $info[0] * $info[1] > 16000000
            || ! in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['foto' => 'La fotografía no es válida o supera 4 MiB / 16 megapíxeles.']);
        }

        return $bytes;
    }

    public function analyze(string $bytes, bool $extract = false): array
    {
        $url = rtrim((string) config('face_recognition.url'), '/');
        $token = (string) config('face_recognition.token');
        if ($url === '' || $token === '') {
            throw new HttpException(503, 'La validación facial no está disponible. Intenta más tarde.');
        }
        try {
            $response = Http::withToken($token)->acceptJson()->withoutRedirecting()
                ->connectTimeout(2)->timeout(config('face_recognition.timeout', 15))
                ->withBody($bytes, 'application/octet-stream')->post($url.($extract ? '/extract' : '/check'));
        } catch (ConnectionException $e) {
            throw new HttpException(503, 'No pudimos conectar con el validador facial. Vuelve a intentar.');
        }
        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ! is_bool($data['accepted'] ?? null)) {
            throw new HttpException(503, 'El validador facial está ocupado o no está disponible. Vuelve a intentar.');
        }
        if (! $data['accepted']) {
            $message = match ($data['reason'] ?? '') {
                'no_face' => 'No encontramos un rostro. Mira de frente a la cámara.',
                'multiple_faces' => 'Debe aparecer una sola persona en la fotografía.',
                'blurred' => 'La foto está borrosa. Mantente quieto y toma otra.',
                'poor_lighting' => 'Mejora la iluminación y vuelve a tomar la foto.',
                'face_cut_off' => 'Tu rostro está cortado. Aléjate un poco.',
                'face_too_small' => 'Acerca un poco tu rostro a la cámara.',
                'face_too_large' => 'Aléjate hasta que tu rostro quede dentro del óvalo.',
                default => 'No pudimos procesar tu rostro. Toma otra foto clara y de frente.',
            };
            throw ValidationException::withMessages(['foto' => $message]);
        }
        if ($extract) {
            $vector = $data['embedding'] ?? null;
            $valid = is_array($vector) && array_is_list($vector) && count($vector) === 128;
            $norm = 0.0;
            foreach ($valid ? $vector : [] as $value) {
                if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                    $valid = false;
                    break;
                }
                $norm += $value * $value;
            }
            if (! $valid || abs($norm - 1.0) > 0.02 || ($data['model'] ?? null) !== 'sface_yunet'
                || ! is_string($data['model_fingerprint'] ?? null)
                || ! preg_match('/^[a-f0-9]{64}$/D', $data['model_fingerprint'])
                || ! is_string($data['quality_policy'] ?? null) || strlen($data['quality_policy']) > 100) {
                throw new HttpException(503, 'El validador devolvió una plantilla facial inválida. Vuelve a intentar.');
            }
        }

        return $data;
    }
}
