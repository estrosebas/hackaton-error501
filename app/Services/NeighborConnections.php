<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NeighborConnections
{
    private ?array $blockPairs = null;

    public function primeBlocks(array $ids): void
    {
        $this->blockPairs = [];
        foreach (DB::table('neighbor_blocks')->whereIn('user_id', $ids)->orWhereIn('blocked_user_id', $ids)->get() as $block) {
            $this->blockPairs[$block->user_id.':'.$block->blocked_user_id] = true;
        }
    }

    public function blockedBy(int $actor, int $target): bool
    {
        return $this->blockPairs !== null ? isset($this->blockPairs[$actor.':'.$target])
            : DB::table('neighbor_blocks')->where('user_id', $actor)->where('blocked_user_id', $target)->exists();
    }

    public function blocked(int $a, int $b): bool
    {
        if ($this->blockPairs !== null) {
            return $this->blockedBy($a, $b) || $this->blockedBy($b, $a);
        }

        return DB::table('neighbor_blocks')->where(fn ($q) => $q->where('user_id', $a)->where('blocked_user_id', $b))
            ->orWhere(fn ($q) => $q->where('user_id', $b)->where('blocked_user_id', $a))->exists();
    }

    public function neighbor(User $actor, User $target): void
    {
        abort_unless($actor->ubigeo && $actor->ubigeo === $target->ubigeo, 404);
    }

    public function visibleNeighbors(User $actor): Builder
    {
        return User::where('ubigeo', $actor->ubigeo)->whereKeyNot($actor->id)
            ->whereNotIn('id', DB::table('neighbor_blocks')->where('user_id', $actor->id)->select('blocked_user_id'))
            ->whereNotIn('id', DB::table('neighbor_blocks')->where('blocked_user_id', $actor->id)->select('user_id'));
    }

    public function profile(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => $user->demoAvatarUrl(), 'neighborhood' => $user->neighborhood];
    }

    // Shared ordering serializes block changes, invitations and sends.
    public function lockUsers(array $ids): void
    {
        User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }
}
