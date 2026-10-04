<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('platforms', 'position')) {
            Schema::table('platforms', function (Blueprint $table) {
                $table->unsignedTinyInteger('position')->default(0)->index();
            });
        }
    }

    public function down(): void
    {
        // Preserve the column: it may already have existed before this migration.
    }
};
