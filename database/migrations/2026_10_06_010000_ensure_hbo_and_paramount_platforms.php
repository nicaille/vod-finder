<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (require app_path('Support/Platforms.php') as $platform) {
            if (in_array($platform['slug'], ['hbomax', 'paramountplus'], true)) {
                DB::table('platforms')->insertOrIgnore($platform + [
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep shared catalog entries and the users' subscriptions on rollback.
    }
};
