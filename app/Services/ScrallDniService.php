<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScrallDniService
{
    public function lookup(string $dni): array
    {
        if (! preg_match('/^[0-9]{8}$/D', $dni)) {
            throw ValidationException::withMessages(['dni' => 'El DNI debe tener 8 dígitos.']);
        }
        $token = trim((string) config('scrall.basic_token'));
        if ($token === '') {
            throw new HttpException(503, 'La consulta de DNI no está configurada.');
        }
        try {
            $response = Http::acceptJson()->asJson()->withoutRedirecting()
                ->withHeaders(['Authorization' => 'Basic '.$token])
                ->connectTimeout(3)->timeout(config('scrall.timeout', 12))
                ->post(rtrim(config('scrall.base_url'), '/').'/v1/reniec/basico', ['dni' => $dni]);
        } catch (ConnectionException $e) {
            throw new HttpException(503, 'La consulta de DNI no está disponible. Vuelve a intentar.');
        }
        if ($response->status() === 404) {
            throw ValidationException::withMessages(['dni' => 'No encontramos datos para ese DNI.']);
        }
        if (! $response->successful()) {
            // Do not return upstream bodies: they may include credentials or personal data.
            throw new HttpException(503, 'La consulta de DNI no está disponible. Vuelve a intentar.');
        }
        $result = $response->json('result');
        if (! is_array($result) || ($result['D_NUDNI'] ?? null) !== $dni) {
            throw new HttpException(503, 'La consulta de DNI devolvió una respuesta inválida.');
        }
        foreach (['D_PRENOMBRES', 'D_APEPATERNO', 'D_APEMATERNO'] as $field) {
            if (! is_string($result[$field] ?? null) || trim($result[$field]) === '' || mb_strlen($result[$field]) > 120) {
                throw new HttpException(503, 'La consulta de DNI devolvió datos incompletos.');
            }
        }
        $rawDate = $result['D_FENACIMIENTO'] ?? '';
        try {
            $date = is_string($rawDate) ? CarbonImmutable::createFromFormat('!d/m/Y', $rawDate) : null;
            if (! $date || $date->format('d/m/Y') !== $rawDate || $date->isFuture()) {
                throw new \RuntimeException;
            }
        } catch (\Throwable $e) {
            throw new HttpException(503, 'La consulta de DNI devolvió una fecha inválida.');
        }
        $digit = $result['D_DIGITOVERIFICACION'] ?? null;
        if (! is_int($digit) || $digit < 0 || $digit > 9) {
            throw new HttpException(503, 'La consulta de DNI devolvió un dígito inválido.');
        }

        return [
            'dni' => $dni, 'given_names' => trim($result['D_PRENOMBRES']),
            'paternal_surname' => trim($result['D_APEPATERNO']), 'maternal_surname' => trim($result['D_APEMATERNO']),
            'name' => trim($result['D_PRENOMBRES'].' '.$result['D_APEPATERNO'].' '.$result['D_APEMATERNO']),
            'birth_date' => $date->format('Y-m-d'), 'verification_digit' => $digit,
        ];
    }
}
