<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('directory_visible')->default(false);
            $t->boolean('share_real_name')->default(false);
            $t->string('contact_token', 64)->nullable()->unique();
        });
        Schema::create('user_connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_low_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('user_high_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->string('status', 16)->default('pending');
            $t->foreignId('blocked_by')->nullable()->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_low_id', 'user_high_id']);
            $t->index(['user_high_id', 'status']);
        });
        Schema::create('recommendations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 12);
            $t->unsignedBigInteger('tmdb_id');
            $t->json('content');
            $t->text('message')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->index(['recipient_id', 'created_at']);
        });
        Schema::create('social_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('user_connection_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('recommendation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('kind', 32);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });
        Schema::create('social_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('social_event_id')->constrained()->cascadeOnDelete();
            $t->string('channel', 12);
            $t->string('destination_key', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('retry_at')->nullable();
            $t->timestamps();
            $t->unique(['social_event_id', 'channel', 'destination_key'], 'social_delivery_unique');
        });
    }
    public function down(): void
    {
        foreach (['social_deliveries', 'social_events', 'recommendations', 'user_connections'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique('users_contact_token_unique');
            $t->dropColumn(['directory_visible', 'share_real_name', 'contact_token']);
        });
    }
};
