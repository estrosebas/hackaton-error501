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
        $posts = $this->withReadState($this->posts($viewer), $viewer)->limit(30)->get();

        $active = $this->connections->visibleNeighbors($viewer)
            ->where('share_community_presence', true)->where('community_active_at', '>=', now()->subSeconds(90))
            ->latest('community_active_at')->orderBy('id')->limit(20)->get(['id', 'name', 'community_avatar_path']);
        $viewer->refresh();

        return response()->json([
            'data' => $posts->map(fn (CommunityPost $post) => $this->notice($post)),
            'active' => $active->map($this->person(...)),
            'sharing_presence' => $viewer->share_community_presence,
            'my_avatar' => $this->avatarVersion($viewer),
            'has_district' => true,
        ]);
    }

    public function emergencies(Request $request)
    {
        $viewer = $request->user();
        if (! $viewer->ubigeo) {
            return response()->json(['data' => []])->header('Cache-Control', 'private, no-store');
        }

        $posts = $this->withReadState($this->emergencyPosts($viewer), $viewer)
            ->where('community_posts.status', 'open')->get();
        if ($posts->isNotEmpty()) {
            $this->connections->primeBlocks([$viewer->id]);
        }

        return response()->json(['data' => $posts->map(fn (CommunityPost $post) => $this->notice(
            $post,
            redacted: $this->connections->blocked($viewer->id, $post->user_id),
        ))])->header('Cache-Control', 'private, no-store');
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
        abort_unless($viewer->ubigeo, 404);
        $posts = $post->kind === 'emergency' ? $this->emergencyPosts($viewer) : $this->posts($viewer);
        abort_unless($posts->whereKey($post->id)->exists(), 404);
        DB::table('community_notification_reads')->insertOrIgnore(['user_id' => $viewer->id, 'post_id' => $post->id]);

        return response()->noContent();
    }

    private function emergencyPosts(User $viewer): Builder
    {
        return CommunityPost::where('community_posts.ubigeo', $viewer->ubigeo)
            ->where('community_posts.user_id', '!=', $viewer->id)
            ->where('community_posts.kind', 'emergency')->whereNull('community_posts.demo_key');
    }

    private function withReadState(Builder $posts, User $viewer): Builder
    {
        return $posts->leftJoin('community_notification_reads as reads', fn ($join) => $join
            ->on('reads.post_id', '=', 'community_posts.id')->where('reads.user_id', $viewer->id))
            ->select('community_posts.*', 'reads.user_id as reader_id')
            ->with('author:id,name,community_avatar_path')
            ->latest('community_posts.created_at')->orderByDesc('community_posts.id');
    }

    private function notice(CommunityPost $post, bool $redacted = false): array
    {
        return [
            'id' => (string) $post->id,
            'kind' => $post->kind,
            'summary' => $redacted ? 'Se reportó una emergencia en tu distrito.' : Str::limit($post->activity_title ?: Str::squish($post->body), 125),
            'published_at' => $post->created_at->toISOString(),
            'read' => $post->reader_id !== null,
            'author' => $redacted ? null : $this->person($post->author),
        ];
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
