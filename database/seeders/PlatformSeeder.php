<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Platform;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $items = require app_path('Support/Platforms.php');

        foreach ($items as $it) {
            Platform::updateOrCreate(
                ['slug' => $it['slug']],
                [
                    'name' => $it['name'],
                    'position' => $it['position'],
                ]
            );
        }
    }
}
