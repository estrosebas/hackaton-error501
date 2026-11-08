<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Services\CommunityPhoto;
use App\Services\EndorsedFeed;
use App\Services\NeighborConnections;
use App\Services\PersonalizedFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunityController extends Controller
{
    private const KINDS = ['moment', 'need_help', 'offer_help', 'collaboration', 'initiative', 'activity', 'alert', 'emergency'];

    private const IMAGE_RULES = ['file', 'image', 'mimes:jpeg,png,webp', 'dimensions:max_width=2400,max_height=2400'];

    public function __construct(private NeighborConnections $connections) {}

    private function scope(Request $request, CommunityPost $post, bool $respectBlocks = false): void
    {
        $viewer = $request->user();
        abort_unless($viewer->ubigeo && $viewer->ubigeo === $post->ubigeo, 404);
        abort_if(($respectBlocks || $post->kind === 'emergency') && $this->connections->blocked($viewer->id, $post->user_id), 404);
    }

    private function present(CommunityPost $post, Request $request): array
    {
        $photoCount = count($post->publishedPhotoPaths());

        return [
            'id' => (string) $post->id, 'author_id' => $post->user_id, 'author' => $post->author->name, 'avatar' => $post->author->demoAvatarUrl(),
            'neighborhood' => $post->author->neighborhood, 'time' => $post->created_at->diffForHumans(),
            'kind' => $post->kind, 'status' => $post->status, 'owned' => $post->user_id === $request->user()->id,
            'isWelcome' => false, 'paragraphs' => $post->body === '' ? [] : explode("\n", $post->body),
            'has_photo' => $photoCount > 0, 'photo_count' => $photoCount,
            'activity' => $post->activity_starts_at ? [
                'title' => $post->activity_title,
                'starts_at' => $post->activity_starts_at->toISOString(),
                'location' => $post->activity_location,
                'latitude' => $post->latitude !== null ? (float) $post->latitude : null,
                'longitude' => $post->longitude !== null ? (float) $post->longitude : null,
            ] : null,
            'reactions' => $post->getAttribute('reaction_total') ?? DB::table('community_reactions')->where('post_id', $post->id)->count(),
            'welcomed' => $post->getAttribute('my_reaction') ?? DB::table('community_reactions')->where('post_id', $post->id)->where('user_id', $request->user()->id)->exists(),
            'saved' => $post->getAttribute('my_bookmark') ?? DB::table('community_bookmarks')->where('post_id', $post->id)->where('user_id', $request->user()->id)->exists(),
            'comments_count' => $post->comments_count ?? $post->comments()->count(),
            'comments' => [], 'tags' => $post->tags->where('type', 'hashtag')->map(fn ($tag) => $tag->name)->values(),
            'categories' => $post->tags->toBase()->where('type', 'category')->map(fn ($tag) => $tag->name)->merge($post->tags->toBase()->where('type', 'feed_topic')->map(fn ($tag) => config('feed-interests.topics.'.$tag->name.'.label', $tag->name)))->unique()->values(),
            'topics' => $post->tags->where('type', 'feed_topic')->map(fn ($tag) => $tag->name)->values(),
            'recommended' => (int) $post->getAttribute('recommendation_priority') === 1,
            'trending' => $post->created_at->gte(now()->subDays(config('feed-interests.recent_days'))) && (($post->getAttribute('reaction_total') ?? 0) + ($post->comments_count ?? 0)) >= 5,
        ];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['kind' => ['nullable', Rule::in(self::KINDS)], 'saved' => ['nullable', 'boolean'], 'cursor' => ['nullable', 'string', 'max:2048']]);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:100']]);
        $term = trim($search['q'] ?? '');
        $category = trim($search['category'] ?? '');
        $viewer = $request->user();
        abort_unless($viewer->ubigeo, 422, 'Completa tu distrito para ver la comunidad.');
        $posts = CommunityPost::where('ubigeo', $viewer->ubigeo)
            ->where(fn ($q) => $q->where('kind', '!=', 'emergency')->orWhere('user_id', $viewer->id)
                ->orWhereIn('user_id', $this->connections->visibleNeighbors($viewer)->select('id')))
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($request->boolean('saved'), fn ($q) => $q->whereIn('id', DB::table('community_bookmarks')->select('post_id')->where('user_id', $request->user()->id)))
            ->with(['author:id,name,neighborhood,demo_key,demo_avatar', 'tags'])->withCount('comments');
        // Literal substring matching, with case/accent normalization and no SQL interpolation.
        $normalize = fn ($s) => mb_strtolower(Str::ascii($s));
        if ($term !== '') {
            foreach (preg_split('/\s+/u', $normalize($term), flags: PREG_SPLIT_NO_EMPTY) as $word) {
                $posts->whereRaw("strpos(translate(lower(community_posts.body),'áéíóúüñ','aeiouun'), ?) > 0", [$word]);
            }
        }
        if ($category !== '') {
            $topic = collect(config('feed-interests.topics'))->search(fn ($item, $id) => $normalize($id) === $normalize($category) || $normalize($item['label']) === $normalize($category));
            $accepted = array_unique([$normalize($category), $topic ?: $normalize($category)]);
            $posts->whereHas('tags', fn ($q) => $q->whereIn('type', ['category', 'feed_topic', 'hashtag'])->where(function ($q) use ($accepted) {
                foreach ($accepted as $name) {
                    $q->orWhereRaw("translate(lower(COALESCE(name->>'es',name->>'en','')),'áéíóúüñ','aeiouun') = ?", [$name]);
                }
            }));
            [$items,$nextCursor] = app(EndorsedFeed::class)->paginate($posts, $viewer, $data['cursor'] ?? null, [$term, $category, $data['kind'] ?? null, $request->boolean('saved')]);
        } else {
            [$items, $nextCursor] = app(PersonalizedFeed::class)->paginate($posts, $viewer, $data['cursor'] ?? null, $term === '' && ! $request->filled('kind') && ! $request->boolean('saved'));
        }

        $ids = $items->pluck('id');
        $counts = DB::table('community_reactions')->whereIn('post_id', $ids)->selectRaw('post_id, count(*) as total')->groupBy('post_id')->pluck('total', 'post_id');
        $mine = DB::table('community_reactions')->whereIn('post_id', $ids)->where('user_id', $request->user()->id)->pluck('post_id')->flip();
        $saved = DB::table('community_bookmarks')->whereIn('post_id', $ids)->where('user_id', $request->user()->id)->pluck('post_id')->flip();

        return response()->json(['data' => $items->map(function ($post) use ($request, $counts, $mine, $saved) {
            $post->setAttribute('reaction_total', (int) ($counts[$post->id] ?? 0));
            $post->setAttribute('my_reaction', $mine->has($post->id));
            $post->setAttribute('my_bookmark', $saved->has($post->id));

            return $this->present($post, $request);
        }), 'next_cursor' => $nextCursor]);
    }

    public function show(Request $request, CommunityPost $post)
    {
        $this->scope($request, $post, respectBlocks: true);

        return response()->json(['data' => $this->present($post->load(['author', 'tags']), $request)]);
    }

    public function store(Request $request, CommunityPhoto $photos)
    {
        $nonActivity = Rule::prohibitedIf($request->input('kind') !== 'activity');
        $hasPhotos = $request->hasFile('photo') || $request->hasFile('photos');
        $data = $request->validate([
            'body' => [Rule::requiredIf($request->input('kind') !== 'moment' || ! $hasPhotos), 'nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(self::KINDS)],
            'topics' => ['nullable', 'array', 'list', 'max:3'],
            'topics.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('feed-interests.topics')))],
            'photo' => ['nullable', 'prohibits:photos', ...self::IMAGE_RULES, 'max:2048'],
            'photos' => ['nullable', 'array', 'list', 'max:4'],
            'photos.*' => ['required', ...self::IMAGE_RULES, 'max:1536'],
            'activity_title' => [$nonActivity, 'required_with:activity_starts_at,activity_location', 'nullable', 'string', 'max:100'],
            'activity_starts_at' => [$nonActivity, 'required_with:activity_title,activity_location', 'nullable', 'date', 'after:now'],
            'activity_location' => [$nonActivity, 'required_with:activity_title,activity_starts_at', 'nullable', 'string', 'max:160'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], ['body.required' => 'Escribe el texto de tu publicación. Puedes compartir solo una foto en Momento del barrio.']);
        abort_unless($request->user()->ubigeo, 422, 'Completa tu distrito para publicar.');
        $body = trim($data['body'] ?? '');
        preg_match_all('/(?<![\p{L}\p{N}_])#([\p{L}\p{N}_]{1,40})/u', $body, $matches);
        $tags = array_values(array_unique(array_map('mb_strtolower', $matches[1])));
        if (count($tags) > 5) {
            throw ValidationException::withMessages(['body' => 'Usa un máximo de cinco hashtags.']);
        }
        $files = $data['photos'] ?? (isset($data['photo']) ? [$data['photo']] : []);
        $paths = [];
        try {
            foreach ($files as $file) {
                $paths[] = $photos->store($file);
            }
            $post = DB::transaction(function () use ($request, $data, $body, $tags, $paths) {
                $lat = $data['latitude'] ?? ($data['kind'] === 'activity' ? $request->user()->latitude : null);
                $lng = $data['longitude'] ?? ($data['kind'] === 'activity' ? $request->user()->longitude : null);
                $post = CommunityPost::create([
                    'user_id' => $request->user()->id, 'ubigeo' => $request->user()->ubigeo,
                    'kind' => $data['kind'], 'body' => $body, 'photo_path' => $paths[0] ?? null,
                    'photo_paths' => $paths ?: null,
                    'activity_title' => $data['activity_title'] ?? null,
                    'activity_starts_at' => $data['activity_starts_at'] ?? null,
                    'activity_location' => $data['activity_location'] ?? null,
                    'latitude' => $lat,
                    'longitude' => $lng,
                ]);
                $post->syncTagsWithType($tags, 'hashtag');
                $post->syncTagsWithType($data['topics'] ?? [], 'feed_topic');

                return $post;
            });
        } catch (\Throwable $error) {
            if ($paths) {
                Storage::disk('local')->delete($paths);
            }
            throw $error;
        }

        return response()->json(['data' => $this->present($post->load(['author', 'tags']), $request)], 201);
    }

    public function photo(Request $request, CommunityPost $post, string $index = '0')
    {
        $this->scope($request, $post);
        $disk = Storage::disk('local');
        $path = $post->publishedPhotoPaths()[(int) $index] ?? null;
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, 'foto.webp', [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function comments(Request $request, CommunityPost $post)
    {
        $this->scope($request, $post);
        $items = $post->comments()->with('author:id,name,demo_key,demo_avatar')->orderByDesc('id')->cursorPaginate(20);
        $ids = $items->getCollection()->pluck('id');
        $counts = DB::table('community_comment_reactions')->whereIn('comment_id', $ids)->selectRaw('comment_id, count(*) as total')->groupBy('comment_id')->pluck('total', 'comment_id');
        $mine = DB::table('community_comment_reactions')->whereIn('comment_id', $ids)->where('user_id', $request->user()->id)->pluck('comment_id')->flip();

        return response()->json(['data' => $items->getCollection()->map(fn ($comment) => ['id' => (string) $comment->id, 'author_id' => $comment->user_id, 'author' => $comment->author->name, 'avatar' => $comment->author->demoAvatarUrl(), 'text' => $comment->body, 'time' => $comment->created_at->diffForHumans(), 'likes' => (int) ($counts[$comment->id] ?? 0), 'liked' => $mine->has($comment->id)]), 'next_cursor' => $items->nextCursor()?->encode()]);
    }

    public function comment(Request $request, CommunityPost $post)
    {
        $this->scope($request, $post);
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $post->comments()->create(['user_id' => $request->user()->id, 'body' => trim($data['body'])]);

        return response()->json(['message' => 'Comentario publicado.'], 201);
    }

    public function state(Request $request, CommunityPost $post)
    {
        $this->scope($request, $post);
        abort_unless($post->user_id === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'resolved'])]]);
        $post->update($data);

        return response()->json(['data' => $this->present($post->load(['author', 'tags']), $request)]);
    }

    public function destroy(Request $request, CommunityPost $post)
    {
        $this->scope($request, $post);
        abort_unless($post->user_id === $request->user()->id || $request->user()->can('posts.moderate'), 403);
        $post->delete();
        if ($paths = $post->publishedPhotoPaths()) {
            Storage::disk('local')->delete($paths);
        }

        return response()->noContent();
    }

    public function reaction(Request $request, CommunityPost $post)
    {
        return $this->selection($request, $post, 'community_reactions');
    }

    public function bookmark(Request $request, CommunityPost $post)
    {
        return $this->selection($request, $post, 'community_bookmarks');
    }

    private function selection(Request $request, CommunityPost $post, string $table)
    {
        $this->scope($request, $post);
        $data = $request->validate(['selected' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $post, $table, $data) {
            CommunityPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
            $keys = ['post_id' => $post->id, 'user_id' => $request->user()->id];
            if ($data['selected']) {
                DB::table($table)->insertOrIgnore($keys);
            } else {
                DB::table($table)->where($keys)->delete();
            }
        });

        return response()->json(['data' => $this->present($post->load(['author', 'tags']), $request)]);
    }
}
