<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommunityController extends Controller
{
    private const KINDS = ['need_help', 'offer_help', 'collaboration', 'initiative', 'activity', 'alert'];

    private function scope(Request $request, CommunityPost $post): void
    {
        abort_unless($request->user()->ubigeo && $request->user()->ubigeo === $post->ubigeo, 404);
    }

    private function present(CommunityPost $post, Request $request): array
    {
        return [
            'id' => (string) $post->id, 'author' => $post->author->name, 'avatar' => '',
            'neighborhood' => $post->author->neighborhood, 'time' => $post->created_at->diffForHumans(),
            'kind' => $post->kind, 'status' => $post->status, 'owned' => $post->user_id === $request->user()->id,
            'isWelcome' => false, 'paragraphs' => explode("\n", $post->body),
            'reactions' => $post->getAttribute('reaction_total') ?? DB::table('community_reactions')->where('post_id', $post->id)->count(),
            'welcomed' => $post->getAttribute('my_reaction') ?? DB::table('community_reactions')->where('post_id', $post->id)->where('user_id', $request->user()->id)->exists(),
            'saved' => $post->getAttribute('my_bookmark') ?? DB::table('community_bookmarks')->where('post_id', $post->id)->where('user_id', $request->user()->id)->exists(),
            'comments_count' => $post->comments_count ?? $post->comments()->count(),
            'comments' => [], 'tags' => $post->tags->map(fn ($tag) => $tag->name)->values(),
        ];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['kind' => ['nullable', Rule::in(self::KINDS)], 'saved' => ['nullable', 'boolean'], 'cursor' => ['nullable', 'string', 'max:500']]);
        abort_unless($request->user()->ubigeo, 422, 'Completa tu distrito para ver la comunidad.');
        $posts = CommunityPost::where('ubigeo', $request->user()->ubigeo)
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($request->boolean('saved'), fn ($q) => $q->whereIn('id', DB::table('community_bookmarks')->select('post_id')->where('user_id', $request->user()->id)))
            ->with(['author:id,name,neighborhood', 'tags'])->withCount('comments')->orderByDesc('id')->cursorPaginate(10);

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

    public function store(Request $request)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'kind' => ['required', Rule::in(self::KINDS)]]);
        abort_unless($request->user()->ubigeo, 422, 'Completa tu distrito para publicar.');
        preg_match_all('/(?<![\p{L}\p{N}_])#([\p{L}\p{N}_]{1,40})/u', $data['body'], $matches);
        $tags = array_values(array_unique(array_map('mb_strtolower', $matches[1])));
        if (count($tags) > 5) {
            throw ValidationException::withMessages(['body' => 'Usa un máximo de cinco hashtags.']);
        }
        $post = DB::transaction(function () use ($request, $data, $tags) {
            $post = CommunityPost::create(['user_id' => $request->user()->id, 'ubigeo' => $request->user()->ubigeo, 'body' => trim($data['body']), 'kind' => $data['kind']]);
            $post->syncTagsWithType($tags, 'hashtag');

            return $post;
        });

        return response()->json(['data' => $this->present($post->load(['author', 'tags']), $request)], 201);
    }

    public function comments(Request $request, CommunityPost $post)
    {
        $this->scope($request, $post);
        $items = $post->comments()->with('author:id,name')->orderByDesc('id')->cursorPaginate(20);

        return response()->json(['data' => $items->getCollection()->map(fn ($comment) => ['id' => (string) $comment->id, 'author' => $comment->author->name, 'avatar' => '', 'text' => $comment->body, 'time' => $comment->created_at->diffForHumans()]), 'next_cursor' => $items->nextCursor()?->encode()]);
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
