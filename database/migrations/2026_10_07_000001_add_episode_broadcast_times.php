<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('series_episodes', function (Blueprint $table) {
            $table->dateTime('airs_at')->nullable()->index();
            $table->string('calendar_source', 16)->default('tmdb');
            $table->boolean('is_next_announced')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('series_episodes', function (Blueprint $table) {
            $table->dropIndex(['airs_at']);
            $table->dropColumn(['airs_at', 'calendar_source', 'is_next_announced']);
        });
    }
};
