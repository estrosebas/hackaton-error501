<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Services\CommunityPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunityController extends Controller
{
    private const KINDS = ['moment', 'need_help', 'offer_help', 'collaboration', 'initiative', 'activity', 'alert'];

    private const IMAGE_RULES = ['file', 'image', 'mimes:jpeg,png,webp', 'dimensions:max_width=2400,max_height=2400'];

    private function scope(Request $request, CommunityPost $post): void
    {
        abort_unless($request->user()->ubigeo && $request->user()->ubigeo === $post->ubigeo, 404);
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
            ] : null,
            'reactions' => $post->getAttribute('reaction_total') ?? DB::table('community_reactions')->where('post_id', $post->id)->count(),
            'welcomed' => $post->getAttribute('my_reaction') ?? DB::table('community_reactions')->where('post_id', $post->id)->where('user_id', $request->user()->id)->exists(),
            'saved' => $post->getAttribute('my_bookmark') ?? DB::table('community_bookmarks')->where('post_id', $post->id)->where('user_id', $request->user()->id)->exists(),
            'comments_count' => $post->comments_count ?? $post->comments()->count(),
            'comments' => [], 'tags' => $post->tags->where('type', 'hashtag')->map(fn ($tag) => $tag->name)->values(),
            'categories' => $post->tags->where('type', 'category')->map(fn ($tag) => $tag->name)->values(),
        ];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['kind' => ['nullable', Rule::in(self::KINDS)], 'saved' => ['nullable', 'boolean'], 'cursor' => ['nullable', 'string', 'max:500']]);
        abort_unless($request->user()->ubigeo, 422, 'Completa tu distrito para ver la comunidad.');
        $posts = CommunityPost::where('ubigeo', $request->user()->ubigeo)
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($request->boolean('saved'), fn ($q) => $q->whereIn('id', DB::table('community_bookmarks')->select('post_id')->where('user_id', $request->user()->id)))
            ->with(['author:id,name,neighborhood,demo_key,demo_avatar', 'tags'])->withCount('comments')->orderByDesc('id')->cursorPaginate(10);

        $ids = $posts->getCollection()->pluck('id');
        $counts = DB::table('community_reactions')->whereIn('post_id', $ids)->selectRaw('post_id, count(*) as total')->groupBy('post_id')->pluck('total', 'post_id');
        $mine = DB::table('community_reactions')->whereIn('post_id', $ids)->where('user_id', $request->user()->id)->pluck('post_id')->flip();
        $saved = DB::table('community_bookmarks')->whereIn('post_id', $ids)->where('user_id', $request->user()->id)->pluck('post_id')->flip();

        return response()->json(['data' => $posts->getCollection()->map(function ($post) use ($request, $counts, $mine, $saved) {
            $post->setAttribute('reaction_total', (int) ($counts[$post->id] ?? 0));
            $post->setAttribute('my_reaction', $mine->has($post->id));
            $post->setAttribute('my_bookmark', $saved->has($post->id));

            return $this->present($post, $request);
        }), 'next_cursor' => $posts->nextCursor()?->encode()]);
    }

    public function store(Request $request, CommunityPhoto $photos)
    {
        $nonActivity = Rule::prohibitedIf($request->input('kind') !== 'activity');
        $hasPhotos = $request->hasFile('photo') || $request->hasFile('photos');
        $data = $request->validate([
            'body' => [Rule::requiredIf($request->input('kind') !== 'moment' || ! $hasPhotos), 'nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::in(self::KINDS)],
            'photo' => ['nullable', 'prohibits:photos', ...self::IMAGE_RULES, 'max:2048'],
            'photos' => ['nullable', 'array', 'list', 'max:4'],
            'photos.*' => ['required', ...self::IMAGE_RULES, 'max:1536'],
            'activity_title' => [$nonActivity, 'required_with:activity_starts_at,activity_location', 'nullable', 'string', 'max:100'],
            'activity_starts_at' => [$nonActivity, 'required_with:activity_title,activity_location', 'nullable', 'date', 'after:now'],
            'activity_location' => [$nonActivity, 'required_with:activity_title,activity_starts_at', 'nullable', 'string', 'max:160'],
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
                $post = CommunityPost::create([
                    'user_id' => $request->user()->id, 'ubigeo' => $request->user()->ubigeo,
                    'kind' => $data['kind'], 'body' => $body, 'photo_path' => $paths[0] ?? null,
                    'photo_paths' => $paths ?: null,
                    'activity_title' => $data['activity_title'] ?? null,
                    'activity_starts_at' => $data['activity_starts_at'] ?? null,
                    'activity_location' => $data['activity_location'] ?? null,
                ]);
                $post->syncTagsWithType($tags, 'hashtag');

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
