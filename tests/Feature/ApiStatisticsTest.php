<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ApiMonitor;
use App\Services\ApiStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitor_records_minutes_in_utc_and_keeps_counts_without_private_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-10 12:34:56', 'Europe/Paris'));
        $monitor = app(ApiMonitor::class);
        $monitor->record('https://api.themoviedb.org/3/movie/1?api_key=secret', 200);
        $monitor->record('https://api.themoviedb.org/3/movie/2', 429);
        $monitor->record('https://api.themoviedb.org/3/movie/3', null);
        $monitor->record('https://unknown.test/private', 500);
        $this->assertDatabaseCount('api_request_buckets', 1);
        $this->assertDatabaseHas('api_request_buckets', ['service' => 'TMDb', 'bucket_at' => '2026-10-10 10:34:00', 'requests' => 3, 'failures' => 2]);
        $this->assertDatabaseHas('api_health', ['service' => 'TMDb', 'requests' => 3, 'failures' => 2]);
        $this->assertStringNotContainsString('secret', json_encode(DB::table('api_request_buckets')->get()));
    }

    public function test_period_is_paris_local_and_end_is_exclusive_with_zero_buckets(): void
    {
        foreach ([['2026-10-09 21:59:00', 100], ['2026-10-09 22:00:00', 2], ['2026-10-10 21:59:00', 3], ['2026-10-10 22:00:00', 100]] as [$date, $requests]) {
            DB::table('api_request_buckets')->insert(['service' => 'TMDb', 'bucket_at' => $date, 'requests' => $requests, 'failures' => 0]);
        }
        $from = CarbonImmutable::parse('2026-10-10', 'Europe/Paris');
        $to = $from->addDay();
        $report = app(ApiStatistics::class)->report($from, $to, 'hour', 'all');
        $this->assertSame(5, $report['totals']['TMDb']['requests']);
        $this->assertCount(24, $report['labels']);
        $this->assertSame(0, $report['datasets'][0]['requests'][1]);
        $this->assertSame(0, $report['totals']['TVmaze']['requests']);
        $daily = app(ApiStatistics::class)->report($from, $to, 'day', 'TMDb');
        $this->assertSame(['10/10/2026'], $daily['labels']);
        $this->assertSame([5], $daily['datasets'][0]['requests']);
        $this->assertCount(1, $daily['totals']);
    }

    public function test_repeated_hour_on_dst_change_remains_two_distinct_points(): void
    {
        foreach (['2026-10-25 00:15:00', '2026-10-25 01:15:00'] as $date) DB::table('api_request_buckets')->insert(['service' => 'TVmaze', 'bucket_at' => $date, 'requests' => 1, 'failures' => 0]);
        $from = CarbonImmutable::parse('2026-10-25', 'Europe/Paris');
        $report = app(ApiStatistics::class)->report($from, $from->addDay(), 'hour', 'TVmaze');
        $this->assertCount(25, $report['labels']);
        $this->assertContains('25/10/2026 02:00 +02:00', $report['labels']);
        $this->assertContains('25/10/2026 02:00 +01:00', $report['labels']);
        $this->assertSame(2, $report['totals']['TVmaze']['requests']);
    }

    public function test_only_admins_can_view_statistics_and_filters_are_validated(): void
    {
        $this->get('/admin/api-statistics')->assertRedirect('/login');
        $member = User::factory()->create();
        $this->actingAs($member)->get('/admin/api-statistics')->assertForbidden();
        $member->forceFill(['is_admin' => true])->save();
        $this->get('/admin/api-statistics')->assertOk()->assertSee('Statistiques des API')->assertSee('Aucun appel enregistré')->assertHeader('Cache-Control', 'no-store, private');
        foreach (['service=private', 'interval=minute', 'from=invalid', 'from=2026-01-01T00:00&to=2026-10-10T00:00', 'from=2026-10-10T00:00&to=2026-10-09T00:00'] as $query) {
            $this->getJson('/admin/api-statistics?'.$query)->assertUnprocessable();
        }
    }
}
