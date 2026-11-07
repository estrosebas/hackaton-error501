<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dni', 8)->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('ubigeo', 6)->nullable()->index();
            $table->string('neighborhood', 120)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('biometric_consent_at')->nullable();
        });

        Schema::create('face_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('photo_path');
            $table->text('embedding'); // Laravel encrypted:array, never a public field.
            $table->string('model', 80);
            $table->string('model_fingerprint', 64);
            $table->string('quality_policy', 100);
            $table->timestamp('enrolled_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dni', 'phone', 'ubigeo', 'neighborhood', 'latitude', 'longitude', 'biometric_consent_at']);
        });
    }
};
