<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('community_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['comment_id', 'user_id']);
        });
        Schema::create('neighbor_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['user_id', 'blocked_user_id']);
        });
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('direct_key', 50)->nullable()->unique();
            $table->string('name', 80)->nullable();
            $table->timestamps();
        });
        Schema::create('chat_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_id')->default(0);
            $table->timestamps();
            $table->unique(['room_id', 'user_id']);
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['room_id', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['chat_messages', 'chat_members', 'chat_rooms', 'neighbor_blocks', 'community_comment_reactions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
