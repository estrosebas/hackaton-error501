<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    protected $guarded = ['id'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_members', 'room_id')->withPivot('last_read_id')->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }
}
