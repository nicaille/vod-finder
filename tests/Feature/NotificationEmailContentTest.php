<?php

namespace Tests\Feature;

use App\Mail\{NotificationTest, VerifyNotificationEmail};
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationEmailContentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from.address' => 'notifications@example.test', 'mail.from.name' => 'VOD Finder']);
    }

    public function test_smtp_also_sends_both_text_and_html_with_site_signature(): void
    {
        $sent = Mail::mailer('array')->to('recipient@example.test')->send(new NotificationTest());
        $message = $sent->getSymfonySentMessage()->getOriginalMessage();

        $this->assertStringContainsString('envoyé à ta demande', $message->getTextBody());
        $this->assertStringContainsString('VOD Finder', $message->getHtmlBody());
        $this->assertStringContainsString(url('/'), $message->getTextBody());
        $this->assertStringContainsString(route('admin.notification-mail.edit'), $message->getHtmlBody());
    }

    public function test_verification_preserves_the_signed_url_in_text_and_escapes_it_in_html(): void
    {
        $url = url('/email/verify/1/example').'?expires=123&signature=abc';
        $sent = Mail::mailer('array')->to('recipient@example.test')->send(new VerifyNotificationEmail($url));
        $message = $sent->getSymfonySentMessage()->getOriginalMessage();

        $this->assertStringContainsString($url, $message->getTextBody());
        $this->assertStringContainsString(e($url), $message->getHtmlBody());
        $this->assertStringNotContainsString('&amp;', $message->getTextBody());
        $this->assertStringNotContainsString('#notifications', $message->getTextBody());
    }
}
