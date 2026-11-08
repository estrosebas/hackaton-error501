<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedInterestController extends Controller
{
    public function catalog()
    {
        return response()->json(['data' => collect(config('feed-interests.topics'))->map(fn ($topic, $id) => ['id' => $id, 'label' => $topic['label'], 'emoji' => $topic['emoji']])->values()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['interests' => ['present', 'array', 'list', 'max:8'], 'interests.*' => ['required', 'string', 'distinct', Rule::in(array_keys(config('feed-interests.topics')))]]);
        $user = $request->user();
        $user->forceFill(['feed_interests' => $data['interests'], 'feed_interests_completed_at' => now()])->save();

        return response()->json(['data' => $user->only(['feed_interests', 'feed_interests_completed_at'])])->header('Cache-Control', 'no-store');
    }
}
