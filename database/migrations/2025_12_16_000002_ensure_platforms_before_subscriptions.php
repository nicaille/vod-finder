<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Keep the historical filename recorded by existing installations.
        (require __DIR__.'/2025_12_31_000001_create_platforms_table.php')->up();
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
