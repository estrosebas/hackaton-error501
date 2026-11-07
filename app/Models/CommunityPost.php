<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Tags\HasTags;

class CommunityPost extends Model
{
    use HasTags, SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['activity_starts_at' => 'immutable_datetime', 'photo_paths' => 'array'];
    }

    public function publishedPhotoPaths(): array
    {
        return $this->photo_paths ?? ($this->photo_path ? [$this->photo_path] : []);
    }

    protected function activityStartsAt(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null
            ? null
            : Carbon::parse($value)->utc()->toIso8601String());
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(CommunityComment::class, 'post_id');
    }
}
