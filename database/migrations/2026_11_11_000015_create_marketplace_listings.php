<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->string('ubigeo', 6);
            $table->string('neighborhood');
            $table->string('title', 100);
            $table->decimal('price', 8, 2);
            $table->string('category', 40);
            $table->string('condition', 40);
            $table->text('description');
            $table->string('photo_path');
            $table->boolean('sold')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['ubigeo', 'sold', 'id']);
        });

        Schema::create('marketplace_favorites', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'listing_id']);
            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_favorites');
        Schema::dropIfExists('marketplace_listings');
    }
};
