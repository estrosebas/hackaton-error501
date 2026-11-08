<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommunityPhoto;
use App\Services\NeighborConnections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommunityAvatarController extends Controller
{
    public function show(Request $request, User $neighbor, NeighborConnections $connections)
    {
        $viewer = $request->user();
        abort_unless($viewer->ubigeo, 404);
        $path = $viewer->is($neighbor) ? $viewer->community_avatar_path
            : $connections->visibleNeighbors($viewer)->whereKey($neighbor->id)->value('community_avatar_path');
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, 'avatar.webp', [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request, CommunityPhoto $photos)
    {
        abort_unless($request->user()->ubigeo, 422, 'Completa tu distrito.');
        $data = $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=2400,max_height=2400'],
        ]);
        $path = $photos->store($data['photo'], avatar: true);
        try {
            $previous = $this->replace($request->user(), $path);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $previous = $this->replace($request->user(), null);
        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        return response()->noContent();
    }

    private function replace(User $user, ?string $path): ?string
    {
        return DB::transaction(function () use ($user, $path) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->community_avatar_path;
            $locked->community_avatar_path = $path;
            $locked->save();

            return $previous;
        });
    }
}
