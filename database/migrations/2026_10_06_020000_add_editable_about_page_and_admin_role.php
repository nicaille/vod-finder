<?php

use App\Support\AboutContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
        DB::table('site_pages')->insert(AboutContent::defaults() + ['created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
