<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapEvent extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['photo_path', 'location'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime', 'has_cost' => 'boolean', 'points' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
