<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->unsignedBigInteger('tmdb_id');
            $table->string('type', 10);   // movie | tv
            $table->string('title');
            $table->string('poster')->nullable();
            $table->string('year', 4)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'tmdb_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_items');
    }
};
