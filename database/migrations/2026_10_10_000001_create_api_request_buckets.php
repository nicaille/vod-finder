<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_request_buckets', function (Blueprint $table) {
            $table->id();
            $table->string('service', 80);
            // UTC minute: no credentials, URLs or user information.
            $table->dateTime('bucket_at');
            $table->unsignedBigInteger('requests')->default(0);
            $table->unsignedBigInteger('failures')->default(0);
            $table->unique(['service', 'bucket_at']);
            $table->index('bucket_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_buckets');
    }
};
