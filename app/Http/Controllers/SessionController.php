<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['dni' => ['required', 'regex:/^[0-9]{8}$/D'], 'password' => ['required', 'string', 'max:255']]);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['dni' => 'El DNI o la contraseña no son correctos.']);
        }
        $request->session()->regenerate();

        return response()->json(['user' => $request->user()]);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
