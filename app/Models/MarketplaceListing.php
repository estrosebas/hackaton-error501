<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketplaceListing extends Model
{
    protected $fillable = [
        'user_id', 'request_id', 'ubigeo', 'neighborhood', 'title', 'price',
        'category', 'condition', 'description', 'photo_path', 'sold',
    ];

    protected function casts(): array
    {
        return ['sold' => 'boolean'];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'marketplace_favorites', 'listing_id');
    }
}
