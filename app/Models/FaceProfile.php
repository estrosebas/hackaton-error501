<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceProfile extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['embedding', 'photo_path'];

    protected function casts(): array
    {
        return ['embedding' => 'encrypted:array', 'enrolled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
