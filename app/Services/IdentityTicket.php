<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class IdentityTicket
{
    public function issue(array $identity): string
    {
        return Crypt::encryptString(json_encode(['identity' => $identity, 'expires_at' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR));
    }

    public function read(string $ticket, string $dni): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($ticket), true, flags: JSON_THROW_ON_ERROR);
            if (($payload['expires_at'] ?? 0) <= now()->getTimestamp() || ($payload['identity']['dni'] ?? null) !== $dni) {
                throw new \RuntimeException;
            }

            return $payload['identity'];
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['identity_token' => 'Consulta nuevamente tu DNI para continuar con el registro.']);
        }
    }
}
