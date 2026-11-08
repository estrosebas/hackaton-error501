<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PersonalizedFeed
{
    public function paginate(Builder $query, User $viewer, ?string $cursor, bool $personalize): array
    {
        $interests = $viewer->feed_interests ?? [];
        if (! $personalize || ! $interests) {
            $page = $query->orderByDesc('id')->cursorPaginate(10);

            return [$page->getCollection(), $page->nextCursor()?->encode()];
        }
        sort($interests);
        $hash = hash('sha256', json_encode($interests));
        $state = ['u' => $viewer->id, 'h' => $hash, 'z' => $viewer->ubigeo, 'a' => now()->timestamp, 'm' => (int) CommunityPost::max('id')];
        if ($cursor) {
            try {
                $state = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            } catch (DecryptException|\JsonException $error) {
                throw ValidationException::withMessages(['cursor' => 'Actualiza el feed para continuar con tus recomendaciones.']);
            }
            if (($state['u'] ?? null) !== $viewer->id || ($state['h'] ?? null) !== $hash || ($state['z'] ?? null) !== $viewer->ubigeo || ! isset($state['a'], $state['m'], $state['i'], $state['p'])) {
                throw ValidationException::withMessages(['cursor' => 'Tus preferencias cambiaron. Actualiza el feed.']);
            }
        }
        $aliases = [];
        foreach ($interests as $id) {
            $aliases = [...$aliases, $id, ...(config("feed-interests.topics.$id.aliases") ?? [])];
        }
        $aliases = array_values(array_unique(array_map(fn ($s) => mb_strtolower(Str::ascii($s)), $aliases)));
        $placeholders = implode(',', array_fill(0, count($aliases), '?'));
        $normalize = fn ($column) => "translate(lower($column), 'áéíóúüñ', 'aeiouun')";
        $tagName = $normalize("COALESCE(tags.name->>'es', tags.name->>'en', '')");
        $body = $normalize('community_posts.body');
        $pattern = '(^|[^a-z0-9_])('.implode('|', array_map(fn ($a) => preg_quote($a, '/'), $aliases)).')([^a-z0-9_]|$)';
        $cutoff = Carbon::createFromTimestamp($state['a'])->subDays(config('feed-interests.recent_days'))->toDateTimeString();
        $score = "CASE WHEN community_posts.created_at >= ? AND community_posts.kind = 'emergency' THEN 2 WHEN community_posts.created_at >= ? AND (EXISTS (SELECT 1 FROM taggables JOIN tags ON tags.id = taggables.tag_id WHERE taggables.taggable_id = community_posts.id AND taggables.taggable_type = ? AND tags.type IN ('feed_topic','category','hashtag') AND $tagName IN ($placeholders)) OR $body ~ ?) THEN 1 ELSE 0 END";
        $bindings = [$cutoff, $cutoff, (new CommunityPost)->getMorphClass(), ...$aliases, $pattern];
        $query->where('community_posts.id', '<=', $state['m']);
        if ($cursor) {
            $query->where(function ($q) use ($score, $bindings, $state) {
                $q->whereRaw("($score) < ?", [...$bindings, $state['p']])
                    ->orWhere(fn ($same) => $same->whereRaw("($score) = ?", [...$bindings, $state['p']])->where('community_posts.id', '<', $state['i']));
            });
        }
        $rows = $query->selectRaw("($score) AS recommendation_priority", $bindings)->orderByRaw("($score) DESC", $bindings)->orderByDesc('community_posts.id')->limit(11)->get();
        $hasMore = $rows->count() > 10;
        $rows = $rows->take(10)->values();
        $last = $rows->last();
        $next = $hasMore ? Crypt::encryptString(json_encode([...$state, 'i' => $last->id, 'p' => (int) $last->recommendation_priority])) : null;

        return [$rows, $next];
    }
}
