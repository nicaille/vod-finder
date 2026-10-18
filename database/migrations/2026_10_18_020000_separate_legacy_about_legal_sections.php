<?php

use App\Support\AboutContent;
use App\Support\LegalContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $page = DB::table('site_pages')->where('slug', 'about')->lockForUpdate()->first();
            // Never replace legal sections already customized in the new editor.
            if (!$page || $page->terms_body !== LegalContent::terms() || $page->privacy_body !== LegalContent::privacy()) return;
            $parts = AboutContent::splitLegacySections($page->body);
            if ($parts) DB::table('site_pages')->where('id', $page->id)->update($parts);
        });
    }

    public function down(): void
    {
        // Editorial data remains in its current fields when rolling back this data migration.
    }
};
