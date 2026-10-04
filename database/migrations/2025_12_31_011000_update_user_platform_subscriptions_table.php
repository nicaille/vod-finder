<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_platform_subscriptions')) {
            return;
        }

        Schema::table('user_platform_subscriptions', function (Blueprint $table) {

            // ⚠️ Colonnes legacy (tu peux les retirer si tu ne les utilises plus)
            if (!Schema::hasColumn('user_platform_subscriptions', 'is_subscribed')) {
                $table->boolean('is_subscribed')->default(true)->after('platform_id');
            }

            if (!Schema::hasColumn('user_platform_subscriptions', 'access_via_platform_id')) {
                $table->foreignId('access_via_platform_id')
                    ->nullable()
                    ->after('is_subscribed')
                    ->constrained('platforms')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('user_platform_subscriptions', 'notify')) {
                $table->boolean('notify')->default(false)->after('access_via_platform_id');
            }
        });

        // ✅ Unique safe (ne tente pas de recréer si déjà existant)
        $uniqueName = 'ups_user_platform_unique';
        $already = false;

        try {
            $rows = DB::select(
                "SHOW INDEX FROM `user_platform_subscriptions` WHERE Key_name = ?",
                [$uniqueName]
            );
            $already = !empty($rows);
        } catch (\Throwable $e) {
            // si SHOW INDEX échoue, on ne tente pas (on évite un nouveau crash)
            $already = true;
        }

        if (!$already) {
            Schema::table('user_platform_subscriptions', function (Blueprint $table) use ($uniqueName) {
                $table->unique(['user_id', 'platform_id'], $uniqueName);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_platform_subscriptions')) {
            return;
        }

        // Drop unique safe
        try {
            Schema::table('user_platform_subscriptions', function (Blueprint $table) {
                $table->dropUnique('ups_user_platform_unique');
            });
        } catch (\Throwable $e) {
            // silencieux
        }

        Schema::table('user_platform_subscriptions', function (Blueprint $table) {

            if (Schema::hasColumn('user_platform_subscriptions', 'notify')) {
                $table->dropColumn('notify');
            }

            if (Schema::hasColumn('user_platform_subscriptions', 'access_via_platform_id')) {
                $table->dropConstrainedForeignId('access_via_platform_id');
            }

            if (Schema::hasColumn('user_platform_subscriptions', 'is_subscribed')) {
                $table->dropColumn('is_subscribed');
            }
        });
    }
};
