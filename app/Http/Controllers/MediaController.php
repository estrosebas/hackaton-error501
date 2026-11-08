<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    private function summary(Media $media): array
    {
        return ['id' => $media->id, 'file_name' => $media->file_name, 'mime_type' => $media->mime_type,
            'size' => $media->size, 'download_url' => url('/api/v1/media/'.$media->id)];
    }

    private function owned(Request $request, Media $media): void
    {
        abort_unless($media->model_type === $request->user()->getMorphClass() && (int) $media->model_id === (int) $request->user()->id, 404);
    }

    public function index(Request $request)
    {
        return response()->json(['data' => $request->user()->getMedia('attachments')->map(fn ($media) => $this->summary($media))]);
    }

    public function store(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:4096', 'dimensions:max_width=4000,max_height=4000']]);
        $media = $request->user()->addMediaFromRequest('file')->toMediaCollection('attachments', 'local');

        return response()->json(['data' => $this->summary($media)], 201);
    }

    public function show(Request $request, Media $media)
    {
        $this->owned($request, $media);

        return response()->file($media->getPath(), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, Media $media)
    {
        $this->owned($request, $media);
        $media->delete();

        return response()->noContent();
    }
}
