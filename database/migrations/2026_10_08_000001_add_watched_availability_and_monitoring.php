<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('watched_titles', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tmdb_id'); $table->string('type', 10);
            $table->string('title'); $table->string('poster')->nullable(); $table->string('year', 4)->nullable();
            $table->timestamp('watched_at'); $table->timestamps();
            $table->unique(['user_id', 'type', 'tmdb_id']);
        });
        Schema::table('watchlist_items', function (Blueprint $table) {
            $table->json('availability_providers')->nullable();
            $table->timestamp('availability_checked_at')->nullable();
            $table->unsignedInteger('availability_revision')->default(0);
        });
        Schema::create('availability_alerts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('watchlist_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('revision'); $table->unsignedBigInteger('tmdb_id'); $table->string('type', 10);
            $table->string('title'); $table->string('poster')->nullable(); $table->json('providers');
            $table->timestamp('read_at')->nullable(); $table->timestamps();
            $table->unique(['watchlist_item_id', 'revision']);
        });
        Schema::create('availability_deliveries', function (Blueprint $table) {
            $table->id(); $table->foreignId('availability_alert_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10); $table->string('destination_key', 64);
            $table->unsignedInteger('attempts')->default(0); $table->timestamp('sent_at')->nullable(); $table->timestamp('retry_at')->nullable();
            $table->timestamps(); $table->unique(['availability_alert_id', 'channel', 'destination_key'], 'availability_delivery_unique');
        });
        Schema::create('api_health', function (Blueprint $table) {
            $table->id(); $table->string('service')->unique(); $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('failures')->default(0); $table->unsignedSmallInteger('last_status')->nullable();
            $table->timestamp('last_success_at')->nullable(); $table->timestamp('last_failure_at')->nullable(); $table->timestamps();
        });
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->string('status', 20);
            $table->timestamp('started_at'); $table->timestamp('finished_at')->nullable(); $table->string('error_class')->nullable(); $table->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['task_runs', 'api_health', 'availability_deliveries', 'availability_alerts', 'watched_titles'] as $table) Schema::dropIfExists($table);
        Schema::table('watchlist_items', fn (Blueprint $table) => $table->dropColumn(['availability_providers', 'availability_checked_at', 'availability_revision']));
    }
};
