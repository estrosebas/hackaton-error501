<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class NeighborProfileRules
{
    public static function rules(bool $required = false): array
    {
        return [
            'neighbor_profile' => [$required ? 'required' : 'nullable', 'array:bio,skills,interests,availability,can_help'],
            'neighbor_profile.bio' => ['nullable', 'string', 'max:280'],
            'neighbor_profile.skills' => ['array', 'list', 'max:5'],
            'neighbor_profile.skills.*' => ['required', 'string', 'distinct', Rule::in(config('neighbor-profile.skills'))],
            'neighbor_profile.interests' => ['array', 'list', 'max:5'],
            'neighbor_profile.interests.*' => ['required', 'string', 'distinct', Rule::in(config('neighbor-profile.interests'))],
            'neighbor_profile.availability' => ['nullable', 'string', Rule::in(config('neighbor-profile.availability'))],
            'neighbor_profile.can_help' => ['boolean'],
        ];
    }
}
