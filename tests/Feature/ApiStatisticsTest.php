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
        $this->assertSame('', config('services.streaming_availability.direct_key')); // Test fixture ignores real credentials.
        $this->get('/admin/api-statistics')->assertRedirect('/login');
        $member = User::factory()->create();
        $this->actingAs($member)->get('/admin/api-statistics')->assertForbidden();
        $member->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($member);
        $this->get('/admin/api-statistics')->assertOk()->assertSee('Statistiques des API')->assertSee('Aucun appel enregistré')->assertHeader('Cache-Control', 'no-store, private');
        foreach (['service=private', 'interval=minute', 'from=invalid', 'from=2026-01-01T00:00&to=2026-10-10T00:00', 'from=2026-10-10T00:00&to=2026-10-09T00:00'] as $query) {
            $this->getJson('/admin/api-statistics?'.$query)->assertUnprocessable();
        }
    }
    public function test_streaming_monthly_budget_uses_paris_boundaries_and_ignores_other_services_and_future_calls(): void
    {
        $now = CarbonImmutable::parse('2026-10-15 12:00:30', 'Europe/Paris');
        $this->travelTo($now);
        foreach ([
            ['Streaming Availability', '2026-09-30 21:59:00', 2000],
            ['Streaming Availability', '2026-09-30 22:00:00', 150],
            ['Streaming Availability', '2026-10-15 10:00:00', 650],
            ['Streaming Availability', '2026-10-15 11:00:00', 3000],
            ['TMDb', '2026-10-15 10:00:00', 5000],
        ] as [$service, $date, $requests]) {
            DB::table('api_request_buckets')->insert(['service' => $service, 'bucket_at' => $date, 'requests' => $requests, 'failures' => 0]);
        }
        $budget = app(ApiStatistics::class)->streamingMonthlyBudget();
        $this->assertSame(1000, $budget['limit']);
        $this->assertSame(800, $budget['used']);
        $this->assertEquals(80, $budget['percent']);
        $this->assertSame('Budget à surveiller', $budget['state']);
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($user)->get('/admin/api-statistics?service=Streaming+Availability&from=2026-09-01T00:00&to=2026-09-02T00:00')
            ->assertOk()->assertSee('1 000 requêtes par mois')->assertSee('800 appels observés')
            ->assertSee('Estimation sur le mois civil')->assertSee('value="800"', false);
        $this->get('/admin/api-statistics?service=TMDb')->assertOk()->assertDontSee('data-streaming-budget', false);
    }

    public function test_streaming_budget_can_exceed_limit_without_overflowing_progress_or_resetting_early(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 23:59:30', 'Europe/Paris'));
        DB::table('api_request_buckets')->insert(['service' => 'Streaming Availability', 'bucket_at' => '2026-10-31 22:59:00', 'requests' => 1200, 'failures' => 4]);
        $budget = app(ApiStatistics::class)->streamingMonthlyBudget();
        $this->assertSame(1200, $budget['used']);
        $this->assertEquals(120, $budget['percent']);
        $this->assertSame('Budget indicatif atteint', $budget['state']);
        $user = User::factory()->create(); $user->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($user)->get('/admin/api-statistics')->assertOk()->assertSee('value="1000"', false);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'Europe/Paris'));
        $this->assertSame(0, app(ApiStatistics::class)->streamingMonthlyBudget()['used']);
    }

    public function test_direct_and_rapidapi_budgets_are_separate_and_direct_cycle_is_calendar_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00', 'Europe/Paris'));
        config(['services.streaming_availability.key' => 'rapid-private-test', 'services.streaming_availability.direct_key' => 'motn-key-private-test']);
        $monitor = app(ApiMonitor::class);
        $monitor->record('https://streaming-availability.p.rapidapi.com/shows/movie/42', 200);
        $monitor->record('https://api.movieofthenight.com/v4/shows/movie%2F42', 200, ['X-RateLimit-Requests-Remaining' => ['999']]);
        $monitor->record('https://api.movieofthenight.com/v4/countries/fr', 200);
        $direct = app(ApiStatistics::class)->streamingMonthlyBudget(\App\Services\StreamingAvailabilityClient::DIRECT_SERVICE);
        $this->assertSame(2, $direct['used']); $this->assertSame(1000, $direct['limit']);
        $this->assertTrue($direct['calendar_cycle']);
        $this->assertSame(1, app(ApiStatistics::class)->streamingMonthlyBudget()['used']);
        $user = User::factory()->create(); $user->forceFill(['is_admin' => true])->save();
        $this->actingAsConfirmedAdmin($user)->get('/admin/api-statistics')->assertOk()
            ->assertSee('Movie of the Night · direct')->assertSee('2 appels observés')->assertSee('1 appels observés')
            ->assertSee('Remise à zéro annoncée le 1er du mois')->assertDontSee('motn-key-private-test')->assertDontSee('rapid-private-test');
        $this->get('/admin/api-statistics?service=Streaming+Availability+Direct')->assertOk()->assertSee('2 appels observés')
            ->assertDontSee('Streaming Availability · RapidAPI')->assertSee('Cache commun');
        config(['services.streaming_availability.direct_monthly_quota' => 2000]);
        DB::table('api_request_buckets')->where('service', \App\Services\StreamingAvailabilityClient::DIRECT_SERVICE)->update(['requests' => 1500]);
        $adjusted = app(ApiStatistics::class)->streamingMonthlyBudget(\App\Services\StreamingAvailabilityClient::DIRECT_SERVICE);
        $this->assertSame(2000, $adjusted['limit']);
        $this->assertSame('Budget disponible', $adjusted['state']);
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Paris'));
        $this->assertSame(0, app(ApiStatistics::class)->streamingMonthlyBudget(\App\Services\StreamingAvailabilityClient::DIRECT_SERVICE)['used']);
    }

}
