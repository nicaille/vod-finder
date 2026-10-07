<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        (require __DIR__.'/create_lists_table.php')->up();
    }

    public function down(): void
    {
        Schema::dropIfExists('lists');
    }
};
