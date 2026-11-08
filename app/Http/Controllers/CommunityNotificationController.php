<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\User;
use App\Services\NeighborConnections;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunityNotificationController extends Controller
{
    public function __construct(private NeighborConnections $connections) {}

    public function sync(Request $request)
    {
        $viewer = $request->user();
        if (! $viewer->ubigeo) {
            return response()->json(['data' => [], 'active' => [], 'sharing_presence' => false, 'my_avatar' => null, 'has_district' => false]);
        }

        User::whereKey($viewer->id)->where('share_community_presence', true)
            ->toBase()->update(['community_active_at' => now()]);
        $posts = $this->posts($viewer)
            ->leftJoin('community_notification_reads as reads', fn ($join) => $join
                ->on('reads.post_id', '=', 'community_posts.id')->where('reads.user_id', $viewer->id))
            ->select('community_posts.*', 'reads.user_id as reader_id')
            ->with('author:id,name,community_avatar_path')
            ->latest('community_posts.created_at')->orderByDesc('community_posts.id')->limit(30)->get();

        $active = $this->connections->visibleNeighbors($viewer)
            ->where('share_community_presence', true)->where('community_active_at', '>=', now()->subSeconds(90))
            ->latest('community_active_at')->orderBy('id')->limit(20)->get(['id', 'name', 'community_avatar_path']);
        $viewer->refresh();

        return response()->json([
            'data' => $posts->map(fn (CommunityPost $post) => [
                'id' => (string) $post->id,
                'kind' => $post->kind,
                'summary' => Str::limit($post->activity_title ?: Str::squish($post->body), 125),
                'published_at' => $post->created_at->toISOString(),
                'read' => $post->reader_id !== null,
                'author' => $this->person($post->author),
            ]),
            'active' => $active->map($this->person(...)),
            'sharing_presence' => $viewer->share_community_presence,
            'my_avatar' => $this->avatarVersion($viewer),
            'has_district' => true,
        ]);
    }

    public function presence(Request $request)
    {
        $viewer = $request->user();
        abort_unless($viewer->ubigeo, 422, 'Completa tu distrito.');
        $request->validate(['sharing' => ['required', 'boolean']]);
        $sharing = $request->boolean('sharing');
        User::whereKey($viewer->id)->toBase()->update([
            'share_community_presence' => $sharing,
            'community_active_at' => $sharing ? now() : null,
        ]);

        return response()->noContent();
    }

    public function read(Request $request, CommunityPost $post)
    {
        $viewer = $request->user();
        abort_unless($viewer->ubigeo && $this->posts($viewer)->whereKey($post->id)->exists(), 404);
        DB::table('community_notification_reads')->insertOrIgnore(['user_id' => $viewer->id, 'post_id' => $post->id]);

        return response()->noContent();
    }

    private function posts(User $viewer): Builder
    {
        return CommunityPost::where('community_posts.ubigeo', $viewer->ubigeo)
            ->whereIn('community_posts.user_id', $this->connections->visibleNeighbors($viewer)->select('id'))
            ->whereIn('community_posts.kind', ['activity', 'need_help', 'alert'])
            ->whereNull('community_posts.demo_key')->where('community_posts.created_at', '>=', now()->subDays(7));
    }

    private function person(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => $this->avatarVersion($user)];
    }

    private function avatarVersion(User $user): ?string
    {
        return $user->community_avatar_path ? pathinfo($user->community_avatar_path, PATHINFO_FILENAME) : null;
    }
}
