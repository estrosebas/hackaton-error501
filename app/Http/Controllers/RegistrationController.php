<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FaceEnrollment;
use App\Services\FacePhotoCheck;
use App\Services\IdentityTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistrationController extends Controller
{
    public function store(Request $request, FacePhotoCheck $checker, FaceEnrollment $enrollment, IdentityTicket $tickets)
    {
        $data = $request->validate([
            'dni' => ['required', 'regex:/^[0-9]{8}$/D', 'unique:users,dni'],
            'identity_token' => ['required', 'string', 'max:4096'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'ubigeo' => ['required', 'regex:/^[0-9]{6}$/D', 'exists:districts,code'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'between:0,99999999'],
            'location_captured_at' => ['required', 'date', 'before_or_equal:now', 'after:-30 minutes'],
            'biometric_consent' => ['required', 'accepted'],
            'foto' => ['required', 'string', 'max:5600000'],
            'neighbor_profile' => ['nullable', 'array:bio,skills,interests,availability,can_help'],
            'neighbor_profile.bio' => ['nullable', 'string', 'max:280'],
            'neighbor_profile.skills' => ['array', 'list', 'max:5'],
            'neighbor_profile.skills.*' => ['required', 'string', 'distinct', Rule::in(config('neighbor-profile.skills'))],
            'neighbor_profile.interests' => ['array', 'list', 'max:5'],
            'neighbor_profile.interests.*' => ['required', 'string', 'distinct', Rule::in(config('neighbor-profile.interests'))],
            'neighbor_profile.availability' => ['nullable', 'string', Rule::in(config('neighbor-profile.availability'))],
            'neighbor_profile.can_help' => ['boolean'],
        ]);
        $identity = $tickets->read($data['identity_token'], $data['dni']);
        $location = DB::table('districts as d')->join('provinces as p', 'p.code', '=', 'd.province_code')
            ->join('regions as r', 'r.code', '=', 'p.region_code')->where('d.code', $data['ubigeo'])
            ->first(['d.name as district', 'p.name as province', 'r.name as region']);
        $data = array_merge($data, $identity, (array) $location, ['dni_checked_at' => now()]);
        $bytes = $checker->decode($data['foto']);
        $analysis = $checker->analyze($bytes, extract: true);
        unset($data['foto'], $data['biometric_consent'], $data['password_confirmation'], $data['identity_token']);
        $data['biometric_consent_at'] = now();
        // An enrollment failure must not leave an account without its required photo.
        $user = DB::transaction(function () use ($data, $bytes, $analysis, $enrollment) {
            $user = User::create($data);
            $user->assignRole('neighbor');
            $enrollment->save($user, $bytes, $analysis);

            return $user;
        });
        $result = ['user' => $user, 'message' => 'Tu cuenta y tu fotografía están registradas.'];
        if ($request->hasSession()) {
            Auth::login($user);
            $request->session()->regenerate();
        } else {
            $result['token'] = $user->createToken('capacitor', ['*'], now()->addDays(30))->plainTextToken;
            $result['token_type'] = 'Bearer';
        }

        return response()->json($result, 201)->header('Cache-Control', 'no-store');
    }
}
