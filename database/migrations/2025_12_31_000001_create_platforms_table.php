<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('platforms')) {
            return;
        }

        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 50)->unique()->index();
            $table->unsignedTinyInteger('position')->default(0)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        // The earlier dependency migration owns removal, after subscriptions.
    }
};
