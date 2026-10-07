<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('brevo_enabled')->default(false);
            $table->text('brevo_api_key')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_name', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_mail_settings');
    }
};
