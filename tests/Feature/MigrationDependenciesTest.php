<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationDependenciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dependency_tables_are_migrated_before_their_consumers(): void
    {
        $names = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
        $this->assertLessThan(array_search('2025_12_16_000003_create_user_platform_subscriptions_table', $names), array_search('2025_12_16_000002_ensure_platforms_before_subscriptions', $names));
        $this->assertLessThan(array_search('create_list_items_table', $names), array_search('2025_12_31_012000_ensure_lists_before_list_items', $names));
        $this->assertLessThan(array_search('create_list_members_table', $names), array_search('2025_12_31_012000_ensure_lists_before_list_items', $names));
    }

    public function test_dependency_migrations_preserve_existing_platforms_and_lists(): void
    {
        $user = \App\Models\User::factory()->create();
        $platformId = DB::table('platforms')->insertGetId(['name' => 'Existing platform', 'slug' => 'existing', 'position' => 7]);
        $listId = DB::table('lists')->insertGetId(['user_id' => $user->id, 'name' => 'Existing list']);

        foreach (['2025_12_16_000002_ensure_platforms_before_subscriptions', '2025_12_31_000001_create_platforms_table', '2025_12_31_012000_ensure_lists_before_list_items', 'create_lists_table'] as $name) {
            (require database_path('migrations/'.$name.'.php'))->up();
        }

        $this->assertDatabaseHas('platforms', ['id' => $platformId, 'name' => 'Existing platform', 'position' => 7]);
        $this->assertDatabaseHas('lists', ['id' => $listId, 'user_id' => $user->id, 'name' => 'Existing list']);
    }
}
