<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'postgis'")) {
            throw new RuntimeException('Activa PostGIS con scripts/setup-postgis.ps1 antes de migrar.');
        }
        Schema::create('map_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('ubigeo', 6)->index();
            $table->string('category', 24);
            $table->string('title', 120);
            $table->text('description');
            $table->string('place', 180);
            $table->timestampTz('starts_at')->index();
            $table->timestampTz('ends_at')->index();
            $table->boolean('has_cost')->default(false);
            $table->unsignedSmallInteger('points');
            $table->string('frame', 24)->nullable();
            $table->string('photo_path')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE map_events ADD COLUMN location geography(Point,4326) NOT NULL');
        DB::statement('CREATE INDEX map_events_location_gist ON map_events USING GIST (location)');
        DB::statement('ALTER TABLE map_events ADD CONSTRAINT map_events_valid_dates CHECK (ends_at > starts_at)');
        Schema::create('map_event_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('joined_at');
            $table->timestampTz('verified_at')->nullable()->index();
            $table->decimal('distance_meters', 8, 2)->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->unique(['map_event_id', 'user_id']);
        });
        Schema::create('empathy_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('points');
            $table->timestampTz('awarded_at');
            $table->unique(['map_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empathy_rewards');
        Schema::dropIfExists('map_event_attendances');
        Schema::dropIfExists('map_events');
    }
};
