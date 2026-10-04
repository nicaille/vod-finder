<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformPositionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_10_05_000001_add_position_to_existing_platforms_table.php');
    }

    public function test_legacy_platforms_are_upgraded_without_losing_subscriptions(): void
    {
        $platform = Platform::create(['name' => 'Netflix', 'slug' => 'netflix', 'position' => 1]);
        $user = User::factory()->create();
        $user->platformSubscriptions()->attach($platform, ['is_active' => true, 'notify_opt_in' => true]);
        Schema::table('platforms', function ($table) {
            $table->dropIndex(['position']);
            $table->dropColumn('position');
        });

        $this->migration()->up();

        $this->assertDatabaseHas('platforms', ['id' => $platform->id, 'slug' => 'netflix', 'position' => 0]);
        $this->assertDatabaseHas('user_platform_subscriptions', ['user_id' => $user->id, 'platform_id' => $platform->id, 'is_active' => 1, 'notify_opt_in' => 1]);
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Netflix');
        $this->app['auth']->guard()->logout();
        $this->get('/register')->assertOk()->assertSee('Netflix');
    }

    public function test_existing_positions_are_preserved_including_on_rollback(): void
    {
        $platform = Platform::create(['name' => 'Netflix', 'slug' => 'netflix', 'position' => 7]);
        $migration = $this->migration();
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertDatabaseHas('platforms', ['id' => $platform->id, 'position' => 7]);
    }
}
