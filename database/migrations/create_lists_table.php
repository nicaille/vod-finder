<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained() // users
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Liste publique ou non
            $table->boolean('is_public')->default(false);

            // Liste collaborative ou non
            $table->boolean('is_collaborative')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lists');
    }
};
