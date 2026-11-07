<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FaceEnrollment;
use App\Services\FacePhotoCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class RegistrationController extends Controller
{
    public function store(Request $request, FacePhotoCheck $checker, FaceEnrollment $enrollment)
    {
        $data = $request->validate([
            'dni' => ['required', 'regex:/^[0-9]{8}$/D', 'unique:users,dni'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'ubigeo' => ['required', 'regex:/^[0-9]{6}$/D'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'biometric_consent' => ['required', 'accepted'],
            'foto' => ['required', 'string', 'max:5600000'],
        ]);
        $bytes = $checker->decode($data['foto']);
        $analysis = $checker->analyze($bytes, extract: true);
        unset($data['foto'], $data['biometric_consent'], $data['password_confirmation']);
        $data['biometric_consent_at'] = now();
        // An enrollment failure must not leave an account without its required photo.
        $user = DB::transaction(function () use ($data, $bytes, $analysis, $enrollment) {
            $user = User::create($data);
            $enrollment->save($user, $bytes, $analysis);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user, 'message' => 'Tu cuenta y tu fotografía están registradas.'], 201);
    }
}
