<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('share_community_presence')->default(false);
            $table->timestamp('community_active_at')->nullable();
            $table->string('community_avatar_path')->nullable();
            $table->index(['ubigeo', 'community_active_at']);
        });

        Schema::create('community_notification_reads', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->index()->constrained('community_posts')->cascadeOnDelete();
            $table->primary(['user_id', 'post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_notification_reads');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['ubigeo', 'community_active_at']);
            $table->dropColumn(['share_community_presence', 'community_active_at', 'community_avatar_path']);
        });
    }
};
