<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracked_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->unique();
            $table->string('name');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
        Schema::create('series_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracked_series_id')->constrained()->cascadeOnDelete();
            $table->boolean('alerts_enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'tracked_series_id']);
        });
        Schema::create('series_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_series_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('season_number');
            $table->unsignedInteger('episode_number');
            $table->string('name');
            $table->date('air_date')->nullable()->index();
            $table->timestamps();
            $table->unique(['tracked_series_id', 'season_number', 'episode_number'], 'series_episode_number_unique');
        });
        Schema::create('episode_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_episode_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'series_episode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_alerts');
        Schema::dropIfExists('series_episodes');
        Schema::dropIfExists('series_follows');
        Schema::dropIfExists('tracked_series');
    }
};
