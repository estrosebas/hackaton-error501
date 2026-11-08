<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EndorsedFeed
{
    public function paginate(Builder $query, User $viewer, ?string $cursor, array $filters): array
    {
        $signature = hash('sha256', json_encode([$viewer->id, $viewer->ubigeo, $filters]));
        if ($cursor) {
            try {
                $state = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['cursor' => 'Actualiza la búsqueda para continuar.']);
            }
            if (($state['s'] ?? null) !== $signature || ! is_int($state['o'] ?? null) || $state['o'] < 0 || ! is_string($state['k'] ?? null)) {
                throw ValidationException::withMessages(['cursor' => 'La búsqueda cambió. Actualiza el feed.']);
            }
            $ids = Cache::get('endorsed-feed:'.$state['k']);
            if (! is_array($ids)) {
                throw ValidationException::withMessages(['cursor' => 'La búsqueda venció. Actualiza el feed.']);
            }
        } else {
            $ranked = clone $query;
            $ids = $ranked->select('community_posts.id')->selectRaw('(SELECT count(*) FROM neighbor_endorsements e WHERE e.neighbor_id=community_posts.user_id) AS neighbor_votes')->reorder()->orderByDesc('neighbor_votes')->orderByDesc('community_posts.id')->limit(5001)->get()->pluck('id')->all();
            if (count($ids) > 5000) {
                throw ValidationException::withMessages(['category' => 'Hay demasiados resultados. Añade palabras para afinar la búsqueda.']);
            }
            $state = ['s' => $signature, 'k' => (string) Str::uuid(), 'o' => 0];
            Cache::put('endorsed-feed:'.$state['k'], $ids, now()->addMinutes(15));
        }
        $slice = array_slice($ids, $state['o'], 10);
        $rows = $slice ? $query->whereIn('community_posts.id', $slice)->get()->sortBy(fn ($post) => array_search($post->id, $slice))->values() : collect();
        $state['o'] += 10;
        $next = $state['o'] < count($ids) ? Crypt::encryptString(json_encode($state)) : null;

        return [$rows, $next];
    }
}
