<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiSessionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['dni' => ['required', 'regex:/^[0-9]{8}$/D'], 'password' => ['required', 'string', 'max:255']]);
        $user = User::where('dni', $data['dni'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['dni' => 'El DNI o la contraseña no son correctos.']);
        }

        return response()->json(['user' => $user, 'token_type' => 'Bearer',
            'token' => $user->createToken('capacitor', ['*'], now()->addDays(30))->plainTextToken])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
