<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_web')->default(false);
        });
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('public_key');
            $table->string('auth_token');
            $table->timestamps();
        });
        Schema::create('alert_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_alert_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->char('destination_key', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('retry_at')->nullable();
            $table->timestamps();
            $table->unique(['episode_alert_id', 'channel', 'destination_key'], 'alert_delivery_destination_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_deliveries');
        Schema::dropIfExists('push_subscriptions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notify_email', 'notify_web']));
    }
};
