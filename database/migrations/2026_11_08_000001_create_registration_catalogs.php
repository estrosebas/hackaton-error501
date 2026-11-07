<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->string('code', 2)->primary();
            $table->string('name', 120);
        });
        Schema::create('provinces', function (Blueprint $table) {
            $table->string('code', 4)->primary();
            $table->string('region_code', 2);
            $table->string('name', 120);
            $table->foreign('region_code')->references('code')->on('regions')->restrictOnDelete();
        });
        Schema::create('districts', function (Blueprint $table) {
            $table->string('code', 6)->primary();
            $table->string('province_code', 4);
            $table->string('name', 150);
            $table->foreign('province_code')->references('code')->on('provinces')->restrictOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('given_names', 120)->nullable();
            $table->string('paternal_surname', 120)->nullable();
            $table->string('maternal_surname', 120)->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('verification_digit')->nullable();
            $table->timestamp('dni_checked_at')->nullable();
            $table->string('region', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('district', 150)->nullable();
            $table->decimal('location_accuracy', 10, 2)->nullable();
            $table->timestamp('location_captured_at')->nullable();
            $table->foreign('ubigeo')->references('code')->on('districts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['ubigeo']);
            $table->dropColumn(['given_names', 'paternal_surname', 'maternal_surname', 'birth_date', 'verification_digit', 'dni_checked_at', 'region', 'province', 'district', 'location_accuracy', 'location_captured_at']);
        });
        Schema::dropIfExists('districts');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
    }
};
