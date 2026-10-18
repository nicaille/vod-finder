<?php

use App\Support\LegalContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->text('terms_body')->nullable();
            $table->text('privacy_body')->nullable();
        });
        // Preserve the existing presentation, including any administrator edits.
        DB::table('site_pages')->where('slug', 'about')->update([
            'terms_body' => LegalContent::terms(),
            'privacy_body' => LegalContent::privacy(),
        ]);
    }

    public function down(): void
    {
        Schema::table('site_pages', fn (Blueprint $table) => $table->dropColumn(['terms_body', 'privacy_body']));
    }
};
