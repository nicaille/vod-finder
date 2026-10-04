<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 80)->nullable()->after('name');
            }

            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 80)->nullable()->after('first_name');
            }

            if (!Schema::hasColumn('users', 'nickname')) {
                $table->string('nickname', 80)->nullable()->after('last_name');
            }

            if (!Schema::hasColumn('users', 'notify_opt_in')) {
                $table->boolean('notify_opt_in')->default(true)->after('nickname');
            }

            // Optionnel: si tu veux l'utiliser plus tard (tu l’as déjà dans $fillable/$casts)
            if (!Schema::hasColumn('users', 'notify_platform_updates')) {
                $table->boolean('notify_platform_updates')->default(true)->after('notify_opt_in');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'notify_platform_updates')) {
                $table->dropColumn('notify_platform_updates');
            }

            if (Schema::hasColumn('users', 'notify_opt_in')) {
                $table->dropColumn('notify_opt_in');
            }

            if (Schema::hasColumn('users', 'nickname')) {
                $table->dropColumn('nickname');
            }

            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }

            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
        });
    }
};
