<?php

namespace App\Http\Controllers;

use App\Mail\NotificationTest;
use App\Models\NotificationMailSetting;
use App\Services\NotificationEmailSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminNotificationMailController extends Controller
{
    public function edit()
    {
        $settings = NotificationMailSetting::find(1);
        return response()->view('admin.notification-mail', [
            'enabled' => $settings?->brevo_enabled ?? false,
            'keyConfigured' => filled($settings?->getRawOriginal('brevo_api_key')),
            'senderEmail' => $settings?->sender_email ?? '',
            'senderName' => $settings?->sender_name ?? 'VOD Finder',
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request)
    {
        $settings = NotificationMailSetting::firstOrNew(['id' => 1]);
        $validator = Validator::make($request->all(), [
            'brevo_enabled' => ['required', 'boolean'],
            'brevo_api_key' => ['nullable', 'string', 'max:1000', 'regex:/^[A-Za-z0-9._-]+$/D'],
            'sender_email' => ['nullable', 'required_if:brevo_enabled,1', 'email', 'max:255'],
            'sender_name' => ['nullable', 'required_if:brevo_enabled,1', 'string', 'max:100'],
        ]);
        $validator->after(function ($validator) use ($request, $settings) {
            if ($request->boolean('brevo_enabled') && !$request->filled('brevo_api_key') && !filled($settings->getRawOriginal('brevo_api_key'))) {
                $validator->errors()->add('brevo_api_key', 'Renseigne une clé API Brevo pour activer ce service.');
            }
        });
        $data = $validator->validate();
        if (empty($data['brevo_api_key'])) unset($data['brevo_api_key']);
        $settings->fill($data)->save();
        return redirect()->route('admin.notification-mail.edit')->with('status', 'Configuration des notifications e-mail enregistrée.');
    }

    public function test(Request $request, NotificationEmailSender $sender)
    {
        $settings = NotificationMailSetting::find(1);
        if (!$settings || !filled($settings->getRawOriginal('brevo_api_key')) || !$settings->sender_email || !$settings->sender_name) {
            return back()->withErrors(['brevo_test' => 'Enregistre d’abord la clé API et l’expéditeur.']);
        }
        try {
            $sender->sendBrevo($request->user()->email, new NotificationTest(), $settings);
        } catch (\Throwable) {
            return back()->withErrors(['brevo_test' => 'Brevo n’a pas accepté le test. Vérifie la clé API, l’expéditeur validé dans Brevo et l’accès réseau HTTPS.']);
        }
        return back()->with('status', 'Brevo a accepté le message de test destiné à '.$request->user()->email.'. Vérifie sa réception, y compris les courriers indésirables.');
    }
}
