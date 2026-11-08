<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('map_events', fn (Blueprint $table) => $table->char('demo_photo_hash', 64)->nullable());
    }

    public function down(): void
    {
        Schema::table('map_events', fn (Blueprint $table) => $table->dropColumn('demo_photo_hash'));
    }
};
