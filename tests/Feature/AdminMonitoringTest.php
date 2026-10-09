<?php

namespace Tests\Feature;

use App\Models\{User, ApiHealth, TaskRun};
use App\Services\{ApiMonitor, TaskMonitor, AdminLogReader};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, File};
use Tests\TestCase;

class AdminMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(); $user->forceFill(['is_admin'=>true])->save(); return $user;
    }

    public function test_monitoring_and_private_data_are_only_available_to_admins(): void
    {
        $member = User::factory()->create();
        foreach (['/admin/health','/admin/logs','/admin/user-data','/admin/user-data/'.$member->id] as $url) {
            $this->get($url)->assertRedirect('/login');
            $this->actingAs($member)->get($url)->assertForbidden(); $this->app['auth']->forgetGuards();
        }
    }

    public function test_admin_can_consult_all_profile_sections_without_credentials(): void
    {
        $member = User::factory()->create(['first_name'=>'Camille','last_name'=>'Dupont','nickname'=>'Testeur']);
        $member->forceFill(['contact_token'=>str_repeat('x',48)])->save();
        $member->watchlist()->create(['tmdb_id'=>42,'type'=>'movie','title'=>'Titre privé']);
        $member->watchedTitles()->create(['tmdb_id'=>43,'type'=>'tv','title'=>'Série privée','watched_at'=>now()]);
        $member->favorites()->create(['tmdb_id'=>42,'type'=>'movie']);
        $list=$member->lists()->create(['name'=>'Liste vide privée']);
        $this->actingAs($this->admin())->get('/admin/user-data?q=Testeur')->assertOk()->assertSee($member->email)->assertHeader('Cache-Control','no-store, private');
        $this->get('/admin/user-data/'.$member->id)->assertOk()->assertSee('Camille')->assertSee('Dupont')->assertDontSee($member->password)->assertDontSee($member->contact_token);
        foreach (['playlist','watched','favorites','lists','series','subscriptions','recommendations','contacts'] as $tab) $this->get('/admin/user-data/'.$member->id.'?tab='.$tab)->assertOk();
        $this->get('/admin/user-data/'.$member->id.'?tab=playlist')->assertSee('Titre privé');
        $this->get('/admin/user-data/'.$member->id.'?tab=watched')->assertSee('Série privée');
        $this->get('/admin/user-data/'.$member->id.'?tab=lists')->assertSee('Liste vide privée');
        $this->get('/admin/user-data/'.$member->id.'?tab=password')->assertSessionHasErrors('tab');
    }

    public function test_api_monitor_records_http_failures_and_recovers_without_storing_request_secrets(): void
    {
        app(ApiMonitor::class)->record('https://api.themoviedb.org/3/search/movie?api_key=private-secret',503);
        app(ApiMonitor::class)->record('https://api.themoviedb.org/3/search/movie?api_key=private-secret',200);
        app(ApiMonitor::class)->record('https://api.brevo.com/v3/smtp/email',null);
        $api=ApiHealth::where('service','TMDb')->first();
        $this->assertSame(2,$api->requests); $this->assertSame(1,$api->failures); $this->assertNotNull($api->last_failure_at); $this->assertNotNull($api->last_success_at);
        $this->assertStringNotContainsString('private-secret',$api->toJson());
        Http::fake(['*'=>Http::response([],429)]);
        \App\Support\ExternalApiClient::make()->get('https://api.tvmaze.com/shows/1');
        $this->assertDatabaseHas('api_health',['service'=>'TVmaze','last_status'=>429,'failures'=>1]);
        $this->actingAs($this->admin())->get('/admin/health')->assertOk()->assertSee('TMDb')->assertSee('Brevo')->assertDontSee('private-secret');
    }

    public function test_task_exception_is_recorded_without_exception_message(): void
    {
        try { app(TaskMonitor::class)->run('test',fn () => throw new \RuntimeException('secret-value')); } catch (\RuntimeException $e) {}
        $task=TaskRun::first(); $this->assertSame('failed',$task->status); $this->assertNotNull($task->finished_at); $this->assertStringNotContainsString('secret-value',$task->toJson());
    }

    public function test_log_reader_is_bounded_escaped_redacted_and_rejects_traversal_or_symlinks(): void
    {
        $original=$this->app->storagePath(); $dir=sys_get_temp_dir().'/vod-log-test-'.bin2hex(random_bytes(6));
        File::makeDirectory($dir.'/logs',0755,true); $this->app->useStoragePath($dir);
        try {
            file_put_contents($dir.'/logs/test.log',str_repeat('old-line'."\n",40000)."[2026-10-08 12:00:00] local.INFO: ordinary\n[2026-10-08 12:01:00] local.ERROR: <script>alert(1)</script> api_key=secret-123 password=hidden-456 Authorization: Bearer token-789\n");
            symlink($dir.'/logs/test.log',$dir.'/logs/link.log');
            $this->actingAs($this->admin())->get('/admin/logs?file=test.log&level=error')->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false)->assertDontSee('secret-123')->assertDontSee('hidden-456')->assertDontSee('token-789')->assertDontSee('ordinary');
            $this->get('/admin/logs?file=../../.env')->assertNotFound();
            $this->get('/admin/logs?file=link.log')->assertNotFound();
            $this->assertLessThanOrEqual(500,count(explode("\n",app(AdminLogReader::class)->read('test.log'))));
        } finally { $this->app->useStoragePath($original); File::deleteDirectory($dir); }
    }
}
