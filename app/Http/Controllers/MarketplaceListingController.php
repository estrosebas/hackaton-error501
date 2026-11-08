<?php

namespace App\Http\Controllers;

use App\Models\MarketplaceListing;
use App\Models\User;
use App\Services\CommunityPhoto;
use App\Services\NeighborConnections;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MarketplaceListingController extends Controller
{
    private const CATEGORIES = ['Bicicletas', 'Hogar', 'Moda', 'Tecnología', 'Otros'];

    private const CONDITIONS = ['Como nuevo', 'Buen estado', 'Con detalles'];

    private const PAGE_SIZE = 24;

    public function __construct(private NeighborConnections $connections) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'scope' => ['sometimes', Rule::in(['nearby', 'neighborhood'])],
            'view' => ['sometimes', Rule::in(['all', 'mine', 'saved'])],
            'cursor' => ['sometimes', 'integer', 'min:1'],
        ]);
        $actor = $request->user();
        $view = $data['view'] ?? 'all';
        $query = $this->listingQuery($actor);

        if ($view === 'mine') {
            $query->where('user_id', $actor->id);
        } else {
            $this->inDistrict($query, $actor);
            if (($data['scope'] ?? 'nearby') === 'neighborhood') {
                $query->whereRaw('lower(neighborhood) = lower(?)', [$actor->neighborhood]);
            }
            if ($view === 'saved') {
                $query->whereHas('favorites', fn (Builder $users) => $users->whereKey($actor->id));
            }
        }
        if (filled($data['q'] ?? null)) {
            $query->whereRaw("strpos(lower(title || ' ' || description || ' ' || category), lower(?)) > 0", [$data['q']]);
        }

        $total = $query->count();
        if (isset($data['cursor'])) {
            $query->where('id', '<', $data['cursor']);
        }
        $page = $query->orderByDesc('id')->limit(self::PAGE_SIZE + 1)->get();
        $items = $page->take(self::PAGE_SIZE);

        return response()->json([
            'items' => $items->map(fn (MarketplaceListing $listing) => $this->present($listing, $actor)),
            'nextCursor' => $page->count() > self::PAGE_SIZE ? (string) $items->last()->id : null,
            'total' => $total,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $listing)
    {
        $actor = $request->user();
        $item = $this->visible($this->listingQuery($actor), $actor)->findOrFail($listing);

        return response()->json($this->present($item, $actor))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, CommunityPhoto $photos)
    {
        $data = $request->validate([
            'requestId' => ['required', 'uuid'],
            'title' => ['required', 'string', 'min:3', 'max:100'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'between:0,999999'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'condition' => ['required', Rule::in(self::CONDITIONS)],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2400,max_height=2400'],
        ]);
        $actor = $request->user();
        $key = ['user_id' => $actor->id, 'request_id' => $data['requestId']];
        $item = MarketplaceListing::where($key)->first();
        $created = false;

        if (! $item) {
            abort_unless($actor->ubigeo && filled($actor->neighborhood), 422, 'Completa tu distrito y barrio para publicar.');
            $path = $photos->store($request->file('photo'));
            try {
                $item = MarketplaceListing::firstOrCreate($key, [
                    'ubigeo' => $actor->ubigeo,
                    'neighborhood' => $actor->neighborhood,
                    'title' => $data['title'],
                    'price' => $data['price'],
                    'category' => $data['category'],
                    'condition' => $data['condition'],
                    'description' => $data['description'],
                    'photo_path' => $path,
                ]);
                $created = $item->wasRecentlyCreated;
            } finally {
                if (! $created) {
                    Storage::disk('local')->delete($path);
                }
            }
        }

        return response()->json($this->present($item->setRelation('seller', $actor), $actor), $created ? 201 : 200);
    }

    public function markSold(Request $request, MarketplaceListing $listing)
    {
        $actor = $request->user();
        abort_unless($listing->user_id === $actor->id, 403);
        $request->validate(['sold' => ['required', 'accepted']]);
        $listing->update(['sold' => true]);

        return response()->json($this->present($listing->load('seller'), $actor));
    }

    public function destroy(Request $request, MarketplaceListing $listing)
    {
        abort_unless($listing->user_id === $request->user()->id, 403);
        $listing->delete();
        Storage::disk('local')->delete($listing->photo_path);

        return response()->noContent();
    }

    public function favorite(Request $request, string $listing)
    {
        $actor = $request->user();
        $request->validate(['saved' => ['required', 'boolean']]);
        $saved = $request->boolean('saved');

        DB::transaction(function () use ($actor, $listing, $saved) {
            $item = $this->inDistrict(MarketplaceListing::query(), $actor)->lockForUpdate()->findOrFail($listing);
            abort_if($item->user_id === $actor->id, 422, 'El anuncio ya está en Mis anuncios.');
            $key = ['listing_id' => $item->id, 'user_id' => $actor->id];
            if ($saved) {
                DB::table('marketplace_favorites')->insertOrIgnore($key);
            } else {
                DB::table('marketplace_favorites')->where($key)->delete();
            }
        });

        return response()->json(['saved' => $saved]);
    }

    public function photo(Request $request, string $listing)
    {
        $item = $this->visible(MarketplaceListing::query(), $request->user())->findOrFail($listing);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($item->photo_path), 404);

        return response()->file($disk->path($item->photo_path), [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function listingQuery(User $actor): Builder
    {
        return MarketplaceListing::with('seller:id,name')
            ->withExists(['favorites as saved' => fn (Builder $users) => $users->whereKey($actor->id)]);
    }

    private function inDistrict(Builder $query, User $actor): Builder
    {
        return $query->where('ubigeo', $actor->ubigeo)->where('sold', false)
            ->where(fn (Builder $sellers) => $sellers->where('user_id', $actor->id)
                ->orWhereIn('user_id', $this->connections->visibleNeighbors($actor)->select('id')));
    }

    private function visible(Builder $query, User $actor): Builder
    {
        return $query->where(fn (Builder $visibility) => $visibility
            ->where('user_id', $actor->id)
            ->orWhere(fn (Builder $district) => $this->inDistrict($district, $actor)));
    }

    private function present(MarketplaceListing $listing, User $actor): array
    {
        return [
            'id' => (string) $listing->id,
            'title' => $listing->title,
            'price' => (float) $listing->price,
            'currency' => 'PEN',
            'category' => $listing->category,
            'condition' => $listing->condition,
            'description' => $listing->description,
            'neighborhood' => $listing->neighborhood,
            'seller' => ['id' => $listing->user_id, 'name' => $listing->seller->name],
            'createdAt' => $listing->created_at->toIso8601String(),
            'owned' => $listing->user_id === $actor->id,
            'sold' => (bool) $listing->sold,
            'saved' => (bool) $listing->saved,
        ];
    }
}
