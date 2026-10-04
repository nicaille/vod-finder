<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_platform_subscriptions', function (Blueprint $table) {
            // si tu as déjà platform_id/user_id, ne les remets pas

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

            // Empêche doublons user/platform
            // Attention: si tu as déjà un unique ailleurs, adapte
            $table->unique(['user_id', 'platform_id'], 'ups_user_platform_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_platform_subscriptions', function (Blueprint $table) {
            // selon ton existant, tu peux choisir de ne pas drop le unique
            $table->dropUnique('ups_user_platform_unique');

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
