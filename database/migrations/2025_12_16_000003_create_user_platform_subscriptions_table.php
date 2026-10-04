<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('user_platform_subscriptions');
    }
};
