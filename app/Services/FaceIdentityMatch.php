<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FaceIdentityMatch
{
    public function validate(User $user, array $analysis): void
    {
        $reference = $user->faceProfile;
        if (! $reference) {
            throw ValidationException::withMessages(['foto' => 'Necesitas una referencia biométrica registrada antes de cambiar tu foto.']);
        }
        if ($reference->model !== $analysis['model'] || ! hash_equals($reference->model_fingerprint, $analysis['model_fingerprint'])) {
            throw new HttpException(503, 'La referencia biométrica no es compatible con el modelo actual. Se requiere revisar tu registro.');
        }
        $stored = $reference->embedding;
        $candidate = $analysis['embedding'];
        if (! is_array($stored) || ! array_is_list($stored) || count($stored) !== 128) {
            throw new HttpException(503, 'La referencia biométrica no está disponible para comparar.');
        }
        $dot = $normA = $normB = 0.0;
        foreach ($stored as $i => $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new HttpException(503, 'La referencia biométrica no es válida.');
            }
            $dot += $value * $candidate[$i];
            $normA += $value * $value;
            $normB += $candidate[$i] * $candidate[$i];
        }
        $threshold = (float) config('face_recognition.match_threshold', 0.5);
        if ($normA <= 0 || $normB <= 0 || ! is_finite($threshold) || $threshold <= 0 || $threshold > 1) {
            throw new HttpException(503, 'La comparación facial no está disponible.');
        }
        if ($dot / sqrt($normA * $normB) < $threshold) {
            throw ValidationException::withMessages(['foto' => 'El rostro no coincide suficientemente con tu registro. Usa una foto tuya, clara y de frente.']);
        }
    }
}
