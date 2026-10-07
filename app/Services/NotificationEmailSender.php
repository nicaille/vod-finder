<?php

namespace App\Services;

use App\Models\NotificationMailSetting;
use App\Support\ExternalApiClient;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class NotificationEmailSender
{
    public function send(string $recipient, Mailable $mail): void
    {
        $settings = NotificationMailSetting::find(1);
        if ($settings?->brevo_enabled) {
            $this->sendBrevo($recipient, $mail, $settings);
            return;
        }
        // Preserve the configured SMTP provider when Brevo is disabled.
        if (in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
            throw new RuntimeException('Configure a delivery mailer.');
        }
        Mail::to($recipient)->send($mail);
    }

    public function sendBrevo(string $recipient, Mailable $mail, NotificationMailSetting $settings): void
    {
        if (!$settings->brevo_api_key || !$settings->sender_email || !$settings->sender_name) {
            throw new RuntimeException('Brevo configuration is incomplete.');
        }
        $html = $mail->render();
        $response = ExternalApiClient::make()->acceptJson()->withHeaders(['api-key' => $settings->brevo_api_key])
            ->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => ['email' => $settings->sender_email, 'name' => $settings->sender_name],
                'to' => [['email' => $recipient]],
                'subject' => $mail->subject,
                'htmlContent' => $html,
            ]);
        // Do not log Brevo's request/response body: it contains private e-mail content.
        if (!$response->successful() || !is_string($response->json('messageId')) || !$response->json('messageId')) {
            throw new RuntimeException('Brevo did not accept this message.');
        }
    }
}
