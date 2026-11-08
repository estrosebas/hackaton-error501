<?php

namespace App\Http\Controllers;

use App\Services\IdentityTicket;
use App\Services\ScrallDniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Tags\Tag;

class RegistrationCatalogController extends Controller
{
    public function identity(Request $request, ScrallDniService $service, IdentityTicket $tickets)
    {
        $data = $request->validate(['dni' => ['required', 'regex:/^[0-9]{8}$/D']]);
        $identity = $service->lookup($data['dni']);

        return response()->json(['data' => $identity, 'identity_token' => $tickets->issue($identity), 'expires_in' => 600])
            ->header('Cache-Control', 'no-store');
    }

    public function regions()
    {
        return response()->json(['data' => DB::table('regions')->orderBy('name')->get()]);
    }

    public function provinces(string $region)
    {
        abort_unless(DB::table('regions')->where('code', $region)->exists(), 404);

        return response()->json(['data' => DB::table('provinces')->where('region_code', $region)->orderBy('name')->get()]);
    }

    public function districts(string $province)
    {
        abort_unless(DB::table('provinces')->where('code', $province)->exists(), 404);

        return response()->json(['data' => DB::table('districts')->where('province_code', $province)->orderBy('name')->get()]);
    }

    public function taxonomy(Request $request)
    {
        $data = $request->validate(['type' => ['required', 'in:hashtag,category'], 'q' => ['nullable', 'string', 'max:80']]);
        $tags = Tag::withType($data['type'])->orderBy('order_column')->get()
            ->filter(fn ($tag) => ! ($data['q'] ?? '') || mb_stripos($tag->name, $data['q']) !== false)->take(100)->values();

        return response()->json(['data' => $tags]);
    }

    public function profileOptions()
    {
        return response()->json(['data' => config('neighbor-profile')]);
    }

    public function storeTaxonomy(Request $request)
    {
        $data = $request->validate(['type' => ['required', 'in:hashtag,category'], 'name' => ['required', 'string', 'max:80']]);
        $name = trim(ltrim(trim($data['name']), '#'));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Escribe una etiqueta válida.']);
        }
        $tag = Tag::findOrCreate($name, $data['type']);

        return response()->json(['data' => $tag], 201);
    }
}
