<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentReactionController extends Controller
{
    public function __invoke(Request $request, CommunityPost $post, int $comment)
    {
        abort_unless($request->user()->ubigeo && $post->ubigeo === $request->user()->ubigeo, 404);
        $data = $request->validate(['selected' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $post, $comment, $data) {
            $post->comments()->whereKey($comment)->lockForUpdate()->firstOrFail();
            $keys = ['comment_id' => $comment, 'user_id' => $request->user()->id];
            if ($data['selected']) {
                DB::table('community_comment_reactions')->insertOrIgnore($keys);
            } else {
                DB::table('community_comment_reactions')->where($keys)->delete();
            }
        });

        return response()->json(['liked' => $data['selected'], 'likes' => DB::table('community_comment_reactions')->where('comment_id', $comment)->count()]);
    }
}
