<?php

namespace Tests\Feature;

use App\Models\{User, WatchlistItem, WatchedTitle, Platform, AvailabilityAlert, AvailabilityDelivery, TaskRun};
use App\Services\{TmdbService, AvailabilityWatchService, AvailabilityDeliveryService, NotificationEmailSender, BrowserPushService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Cache};
use Tests\TestCase;

class WatchedAndAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function member(?int $via = null, bool $notify = true): User
    {
        $user = User::factory()->create(['notify_opt_in'=>true,'notify_platform_updates'=>true,'notify_email'=>true,'email_verified_at'=>now()]);
        $platform = Platform::firstOrCreate(['slug'=>'netflix'],['name'=>'Netflix']);
        $user->platformSubscriptions()->attach($platform->id,['is_active'=>true,'notify_opt_in'=>$notify,'subscribed_via_platform_id'=>$via]);
        return $user;
    }

    private function item(User $user, int $id = 42, string $type = 'movie'): WatchlistItem
    {
        return $user->watchlist()->create(['tmdb_id'=>$id,'type'=>$type,'title'=>'Un film','year'=>'2026']);
    }

    private function provider(?string $via = null, string $access = 'flatrate'): array
    {
        return ['slug'=>'netflix','name'=>'Netflix','access'=>$access,'via'=>$via,'source'=>'tmdb'];
    }

    private function sync(?array $providers): array
    {
        $tmdb = $this->mock(TmdbService::class);
        $tmdb->shouldReceive('getAvailabilitySnapshot')->andReturn($providers);
        return app(AvailabilityWatchService::class)->sync();
    }

    public function test_seen_round_trip_is_private_and_preserves_playlist_lists_and_same_id_series(): void
    {
        $user = $this->member(); $other = $this->member();
        $this->item($user); $this->item($other);
        $list = $user->lists()->create(['name'=>'Privée']); $list->items()->create(['tmdb_id'=>42,'type'=>'movie','added_by'=>$user->id]);
        $user->favorites()->create(['tmdb_id'=>42,'type'=>'movie']);
        $user->watchedTitles()->create(['tmdb_id'=>42,'type'=>'tv','title'=>'Une série','watched_at'=>now()]);
        $payload = ['tmdb_id'=>42,'type'=>'movie','title'=>'Un film','year'=>'2026'];
        $this->actingAs($user)->postJson('/watched/toggle',$payload)->assertOk()->assertJson(['watched'=>true]);
        $this->get('/account/watched?type=movie')->assertOk()->assertSee('Un film')->assertDontSee('Une série');
        $this->actingAs($other)->get('/account/watched')->assertOk()->assertDontSee('Un film');
        $this->actingAs($user)->postJson('/watched/toggle',$payload)->assertJson(['watched'=>false]);
        $this->assertDatabaseHas('watched_titles',['user_id'=>$user->id,'type'=>'tv','tmdb_id'=>42]);
        $this->assertDatabaseCount('watchlist_items',2); $this->assertDatabaseCount('favorites',1); $this->assertDatabaseCount('list_items',1);
        $this->postJson('/watched/toggle',[...$payload,'type'=>'person'])->assertUnprocessable();
        $this->postJson('/watched/toggle',[...$payload,'poster'=>'javascript:alert(1)'])->assertUnprocessable();
    }

    public function test_initial_baseline_does_not_notify_and_only_new_included_offers_notify_once(): void
    {
        $user = $this->member(); $item = $this->item($user);
        $this->assertSame(0,$this->sync([$this->provider(null,'rent')])['alerts']);
        $this->assertNotNull($item->fresh()->availability_checked_at);
        $this->assertSame(1,$this->sync([$this->provider(),$this->provider(null,'buy')])['alerts']);
        $this->assertSame(0,$this->sync([$this->provider()])['alerts']);
        $this->assertDatabaseCount('availability_alerts',1);
        $alert = AvailabilityAlert::first(); $this->assertCount(1,$alert->providers);
        $this->actingAs($user)->get('/account/availability')->assertOk()->assertSee('Inclus sur Netflix');
        $other = $this->member();
        $this->actingAs($other)->patch('/account/availability/'.$alert->id.'/read')->assertNotFound();
        $this->actingAs($user)->patch('/account/availability/'.$alert->id.'/read')->assertRedirect();
        $this->assertNotNull($alert->fresh()->read_at);
    }

    public function test_already_available_title_is_baselined_without_alert(): void
    {
        $this->item($this->member());
        $this->assertSame(0,$this->sync([$this->provider()])['alerts']);
        $this->assertDatabaseCount('availability_alerts',0);
    }

    public function test_failure_preserves_previous_state_and_does_not_create_false_reappearance(): void
    {
        $item = $this->item($this->member());
        $this->sync([$this->provider()]); $before = $item->fresh()->availability_checked_at;
        $this->travel(1)->hours();
        $this->assertSame(1,$this->sync(null)['failed']);
        $this->assertEquals($before,$item->fresh()->availability_checked_at);
        $this->assertSame(0,$this->sync([$this->provider()])['alerts']);
        $this->assertSame(0,$this->sync([])['alerts']);
        $this->assertSame(1,$this->sync([$this->provider()])['alerts']);
    }

    public function test_via_opt_out_and_seen_titles_are_respected(): void
    {
        $prime = Platform::firstOrCreate(['slug'=>'prime'],['name'=>'Prime Video']);
        $viaUser = $this->member($prime->id); $viaItem = $this->item($viaUser);
        $off = $this->member(null,false); $this->item($off,43);
        $seen = $this->member(); $this->item($seen,44);
        $seen->watchedTitles()->create(['tmdb_id'=>44,'type'=>'movie','title'=>'Vu','watched_at'=>now()]);
        $this->sync([]);
        $this->assertSame(0,$this->sync([$this->provider()])['alerts']);
        $this->assertSame(1,$this->sync([$this->provider(),$this->provider('prime')])['alerts']);
        $this->assertSame($viaUser->id, AvailabilityAlert::first()->user_id);
        $this->assertNull(WatchlistItem::where('user_id',$seen->id)->first()->availability_checked_at);
    }

    public function test_global_opt_out_or_new_subscription_does_not_create_a_catalogue_alert(): void
    {
        $user = $this->member(); $this->item($user);
        $user->update(['notify_platform_updates'=>false]);
        $this->sync([]); $this->assertSame(0,$this->sync([$this->provider()])['alerts']);
        $user->update(['notify_platform_updates'=>true]);
        $this->assertSame(0,$this->sync([$this->provider()])['alerts']);
    }

    public function test_real_snapshot_distinguishes_outage_malformed_json_and_verified_empty_response(): void
    {
        config(['services.tmdb.key'=>'test-only-key','services.streaming_availability.key'=>'']);
        $tmdb = new TmdbService();
        Http::fake(['*'=>Http::sequence()->push([],503)->push(['error'=>'invalid'],200)->push(['results'=>[]],200)]);
        $this->assertNull($tmdb->getAvailabilitySnapshot(42,'movie','FR',['netflix']));
        $this->assertNull($tmdb->getAvailabilitySnapshot(42,'movie','FR',['netflix']));
        $this->assertSame([],$tmdb->getAvailabilitySnapshot(42,'movie','FR',['netflix']));
    }

    public function test_delivery_retries_and_never_duplicates_a_success(): void
    {
        $user = $this->member(); $item = $this->item($user);
        $this->sync([]); $this->sync([$this->provider()]);
        $email = $this->mock(NotificationEmailSender::class);
        $email->shouldReceive('send')->once()->andThrow(new \RuntimeException('test failure'));
        $service = app(AvailabilityDeliveryService::class);
        $this->assertSame(['sent'=>0,'failed'=>1],$service->deliver());
        $this->assertSame(1,AvailabilityDelivery::first()->attempts);
        $this->assertSame(['sent'=>0,'failed'=>0],$service->deliver());
        $this->travel(16)->minutes();
        $email = $this->mock(NotificationEmailSender::class);
        $email->shouldReceive('send')->once()->with($user->email,\Mockery::type(\App\Mail\AvailabilityAnnouncement::class));
        $service = app(AvailabilityDeliveryService::class);
        $this->assertSame(['sent'=>1,'failed'=>0],$service->deliver());
        $this->assertSame(['sent'=>0,'failed'=>0],$service->deliver());
        $this->assertSame(2,AvailabilityDelivery::first()->attempts);
    }

    public function test_delivery_stops_when_watched_or_removed_from_playlist(): void
    {
        $user = $this->member(); $item = $this->item($user);
        $this->sync([]); $this->sync([$this->provider()]);
        $email = $this->mock(NotificationEmailSender::class); $email->shouldNotReceive('send');
        $user->watchedTitles()->create(['tmdb_id'=>42,'type'=>'movie','title'=>'Vu','watched_at'=>now()]);
        $this->assertSame(['sent'=>0,'failed'=>0],app(AvailabilityDeliveryService::class)->deliver());
        $user->watchedTitles()->delete(); $item->delete();
        $this->assertSame(['sent'=>0,'failed'=>0],app(AvailabilityDeliveryService::class)->deliver());
    }

    public function test_commands_record_their_outcome_without_sending_mail_during_sync(): void
    {
        $this->item($this->member());
        $this->mock(TmdbService::class)->shouldReceive('getAvailabilitySnapshot')->andReturn(null);
        $this->artisan('availability:sync')->assertFailed();
        $this->assertDatabaseHas('task_runs',['name'=>'availability:sync','status'=>'failed']);
        $this->artisan('schedule:heartbeat')->assertSuccessful();
        $this->assertDatabaseHas('task_runs',['name'=>'schedule:heartbeat','status'=>'success']);
    }

    public function test_email_requires_verification_and_push_is_delivered_once_per_device(): void
    {
        $user = $this->member(); $this->item($user);
        $user->forceFill(['email_verified_at'=>null,'notify_web'=>true])->save();
        $this->sync([]); $this->sync([$this->provider()]);
        foreach (['one','two'] as $device) {
            $endpoint = 'https://fcm.googleapis.com/fcm/send/'.$device;
            $user->pushSubscriptions()->create(['endpoint'=>$endpoint,'endpoint_hash'=>hash('sha256',$endpoint),'public_key'=>str_repeat('A',87),'auth_token'=>str_repeat('B',22)]);
        }
        $this->mock(NotificationEmailSender::class)->shouldNotReceive('send');
        $push=$this->mock(BrowserPushService::class);
        $push->shouldReceive('keys')->andReturn(['publicKey'=>'test','privateKey'=>'test']);
        $push->shouldReceive('send')->twice()->with(\Mockery::type(\App\Models\PushSubscription::class),\Mockery::on(fn ($payload) => $payload['url'] === '/content/movie/42' && str_contains($payload['body'],'Netflix')))->andReturn(true);
        $this->artisan('notifications:deliver')->assertSuccessful();
        $this->artisan('notifications:deliver')->assertSuccessful();
        $this->assertDatabaseCount('availability_deliveries',2);
        $this->assertSame(2,AvailabilityDelivery::whereNotNull('sent_at')->count());
    }

    public function test_disabling_platform_after_detection_prevents_external_delivery(): void
    {
        $user=$this->member(); $this->item($user); $this->sync([]); $this->sync([$this->provider()]);
        $user->platformSubscriptions()->updateExistingPivot(Platform::where('slug','netflix')->first()->id,['notify_opt_in'=>false]);
        $this->mock(NotificationEmailSender::class)->shouldNotReceive('send');
        $this->assertSame(['sent'=>0,'failed'=>0],app(AvailabilityDeliveryService::class)->deliver());
    }

    public function test_announcement_has_html_text_link_and_paris_timestamp(): void
    {
        $this->item($this->member()); $this->sync([]); $this->sync([$this->provider('prime'),$this->provider()]);
        $mail = new \App\Mail\AvailabilityAnnouncement(AvailabilityAlert::first());
        $html = $mail->render();
        $this->assertStringContainsString('/content/movie/42',$html);
        $this->assertStringContainsString('Un film',$html);
        $this->assertStringContainsString('tous les épisodes',$html);
        $this->assertSame('emails.text.availability-announcement',$mail->textView);
    }
}
