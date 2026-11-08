<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'community_posts', 'community_comments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('demo_key', 100)->nullable()->unique());
        }
        Schema::table('users', fn (Blueprint $table) => $table->string('demo_avatar', 20)->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('demo_avatar'));
        foreach (['users', 'community_posts', 'community_comments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['demo_key']);
                $table->dropColumn('demo_key');
            });
        }
    }
};
