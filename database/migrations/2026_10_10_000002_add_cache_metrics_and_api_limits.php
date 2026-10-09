<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cache_metric_buckets', function (Blueprint $table) {
            $table->id(); $table->string('service', 80); $table->string('usage', 40); $table->dateTime('bucket_at');
            foreach (['hits', 'misses', 'missing', 'expired', 'failures', 'concurrent', 'avoided', 'unknown_hits', 'hit_us', 'miss_us', 'saved_us'] as $field) $table->unsignedBigInteger($field)->default(0);
            $table->unique(['service', 'usage', 'bucket_at'], 'cache_metric_bucket_unique');
            $table->index('bucket_at');
        });
        Schema::create('cache_latency_buckets', function (Blueprint $table) {
            $table->id(); $table->string('service', 80); $table->string('usage', 40); $table->dateTime('bucket_at');
            $table->string('kind', 4); $table->unsignedBigInteger('upper_us'); $table->unsignedBigInteger('samples')->default(0);
            $table->unique(['service', 'usage', 'bucket_at', 'kind', 'upper_us'], 'cache_latency_bucket_unique');
            $table->index('bucket_at');
        });
        Schema::table('api_request_buckets', fn (Blueprint $table) => $table->unsignedBigInteger('rate_limited')->default(0));
        Schema::table('api_health', function (Blueprint $table) {
            $table->unsignedBigInteger('rate_limited')->default(0);
            $table->json('quota_headers')->nullable();
            $table->timestamp('quota_observed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_latency_buckets'); Schema::dropIfExists('cache_metric_buckets');
        Schema::table('api_request_buckets', fn (Blueprint $table) => $table->dropColumn('rate_limited'));
        Schema::table('api_health', fn (Blueprint $table) => $table->dropColumn(['rate_limited', 'quota_headers', 'quota_observed_at']));
    }
};
