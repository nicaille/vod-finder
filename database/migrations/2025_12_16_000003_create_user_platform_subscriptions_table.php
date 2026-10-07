<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_platform_subscriptions')) {
            $this->completeInterruptedMysqlCreation();
            return;
        }

        Schema::create('user_platform_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();

            // Si souscription via un autre provider (ex: Apple TV+ via Canal+)
            $table->foreignId('subscribed_via_platform_id')->nullable()->constrained('platforms')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->boolean('notify_opt_in')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'platform_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    private function completeInterruptedMysqlCreation(): void
    {
        // MySQL commits CREATE TABLE before adding foreign keys. A failed
        // migration can therefore leave its columns and some constraints behind.
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Unexpected existing user_platform_subscriptions table.');
        }
        foreach (['id', 'user_id', 'platform_id', 'subscribed_via_platform_id', 'is_active', 'notify_opt_in', 'created_at', 'updated_at'] as $column) {
            if (!Schema::hasColumn('user_platform_subscriptions', $column)) {
                throw new RuntimeException('Incomplete subscription schema: missing '.$column.'. No data was removed.');
            }
        }

        $tableName = $connection->getTablePrefix().'user_platform_subscriptions';
        foreach (['user_id' => 'users', 'platform_id' => 'platforms', 'subscribed_via_platform_id' => 'platforms'] as $column => $target) {
            $existing = $connection->selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
                [$connection->getDatabaseName(), $tableName, $column, $connection->getTablePrefix().$target]
            );
            if (!$existing) {
                Schema::table('user_platform_subscriptions', function (Blueprint $table) use ($column, $target) {
                    $foreign = $table->foreign($column)->references('id')->on($target);
                    $column === 'subscribed_via_platform_id' ? $foreign->nullOnDelete() : $foreign->cascadeOnDelete();
                });
            }
        }

        foreach (['user_platform_subscriptions_user_id_platform_id_unique' => ['user_id', 'platform_id'], 'user_platform_subscriptions_user_id_is_active_index' => ['user_id', 'is_active']] as $name => $columns) {
            $existing = $connection->selectOne(
                'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$connection->getDatabaseName(), $tableName, $name]
            );
            if (!$existing) {
                Schema::table('user_platform_subscriptions', function (Blueprint $table) use ($name, $columns) {
                    str_ends_with($name, '_unique') ? $table->unique($columns, $name) : $table->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_platform_subscriptions');
    }
};
