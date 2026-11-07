<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Tags\HasTags;

class CommunityPost extends Model
{
    use HasTags, SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(CommunityComment::class, 'post_id');
    }
}
