<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PrivateProfileController extends Controller
{
    private function data(User $user): array
    {
        return $user->only(['name', 'dni', 'given_names', 'paternal_surname', 'maternal_surname', 'birth_date', 'email', 'phone', 'ubigeo', 'region', 'province', 'district', 'neighborhood']);
    }

    public function show(Request $request)
    {
        return response()->json(['data' => $this->data($request->user())])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request)
    {
        $actor = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($actor->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'ubigeo' => ['required', 'string', 'regex:/^[0-9]{6}$/D', 'exists:districts,code'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'current_password' => ['nullable', 'string', 'max:255'],
            'dni' => ['prohibited'], 'name' => ['prohibited'], 'birth_date' => ['prohibited'], 'user_id' => ['prohibited'],
            'region' => ['prohibited'], 'province' => ['prohibited'], 'district' => ['prohibited'],
        ]);
        $data['email'] = mb_strtolower($data['email']);
        if (User::whereKeyNot($actor->id)->whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
            throw ValidationException::withMessages(['email' => 'Ese correo ya está asociado a otra cuenta.']);
        }
        $user = DB::transaction(function () use ($actor, $data) {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($data['email'] === mb_strtolower($user->email)) {
                $data['email'] = $user->email;
            }
            if ($data['email'] !== $user->email) {
                if (! Hash::check($data['current_password'] ?? '', $user->password)) {
                    throw ValidationException::withMessages(['current_password' => 'Confirma tu contraseña actual para cambiar el correo.']);
                }
                DB::table(config('auth.passwords.'.config('auth.defaults.passwords').'.table'))->where('email', $user->email)->delete();
                $user->email_verified_at = null;
            }
            $place = DB::table('districts as d')->join('provinces as p', 'd.province_code', '=', 'p.code')
                ->join('regions as r', 'p.region_code', '=', 'r.code')->where('d.code', $data['ubigeo'])
                ->first(['r.name as region', 'p.name as province', 'd.name as district']);
            unset($data['current_password']);
            $user->fill([...$data, ...(array) $place])->save();

            return $user;
        });

        return response()->json(['data' => $this->data($user)])->header('Cache-Control', 'no-store');
    }
}
