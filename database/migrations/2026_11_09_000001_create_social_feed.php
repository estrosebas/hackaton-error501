<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ubigeo', 6)->index();
            $table->string('kind', 32);
            $table->text('body');
            $table->string('status', 16)->default('open');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['ubigeo', 'id']);
        });
        Schema::create('community_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
        foreach (['community_reactions', 'community_bookmarks'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('community_posts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unique(['post_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['community_bookmarks', 'community_reactions', 'community_comments', 'community_posts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
