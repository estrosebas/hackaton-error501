<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->string('activity_title', 100)->nullable();
            $table->timestampTz('activity_starts_at')->nullable();
            $table->string('activity_location', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn(['activity_title', 'activity_starts_at', 'activity_location']);
        });
    }
};
