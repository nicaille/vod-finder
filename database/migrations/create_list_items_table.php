<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('list_id')
                ->constrained('lists')
                ->cascadeOnDelete();

            // Référence TMDb
            $table->unsignedBigInteger('tmdb_id');

            // movie | tv
            $table->string('type', 10);

            // Qui a ajouté l’item (utile pour les listes collaboratives)
            $table->foreignId('added_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Éviter les doublons dans une liste
            $table->unique(['list_id', 'tmdb_id', 'type'], 'list_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
