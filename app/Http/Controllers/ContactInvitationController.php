<?php

namespace App\Http\Controllers;

use App\Mail\ContactInvitationMail;
use App\Models\{ContactInvitation, User, UserConnection};
use App\Services\{ContactInvitations, NotificationEmailSender};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContactInvitationController extends Controller
{
    public function store(Request $request, ContactInvitations $invitations, NotificationEmailSender $mail)
    {
        $data = $request->validate(['channel' => 'required|in:link,email',
            'email' => 'required_if:channel,email|nullable|email:rfc,filter|max:255']);
        if ($data['channel'] === 'email' && !$request->user()->hasVerifiedEmail()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Confirme ton adresse e-mail dans Mon compte avant d’envoyer une invitation par e-mail. Tu peux aussi partager un lien.']);
        }
        [$invitation, $url] = $invitations->create($request->user());
        if ($data['channel'] === 'email') {
            // A generic response must not bypass blocks or reveal whether an email has an account.
            $target = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();
            $ids = [$request->user()->id, $target?->id]; sort($ids);
            $suppressed = $target && ($target->id === $request->user()->id || UserConnection::where('user_low_id', $ids[0])
                ->where('user_high_id', $ids[1])->whereIn('status', ['blocked', 'declined', 'accepted'])->exists());
            try {
                if (!$suppressed) {
                    $mail->send(mb_strtolower(trim($data['email'])), new ContactInvitationMail($request->user()->socialName(), $url,
                        $invitation->expires_at->timezone('Europe/Paris')->format('d/m/Y à H:i')));
                    $invitation->update(['email_sent_at' => now()]);
                }
            } catch (\Throwable $exception) {
                $invitation->update(['revoked_at' => now()]);
                Log::warning('Contact invitation email failed', ['invitation_id' => $invitation->id, 'exception_class' => get_class($exception)]);
                $message = 'L’invitation n’a pas pu être envoyée. Réessaie plus tard ou partage un lien.';
                return $request->expectsJson() ? response()->json(['message' => $message], 503)
                    : back()->withErrors(['email' => $message]);
            }
            $message = 'Demande d’invitation prise en compte. À réception, ton proche pourra créer son compte ou se connecter, puis accepter.';
            return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
        }
        $message = 'Lien créé : valable 7 jours, pour une seule acceptation.';
        return $request->expectsJson()
            ? response()->json(['url' => $url, 'expires_at' => $invitation->expires_at->toIso8601String(), 'message' => $message], 201)
            : back()->with('invitation_link', $url)->with('status', $message);
    }

    public function show(Request $request, string $token, ContactInvitations $invitations)
    {
        $invitation = $invitations->find($token);
        $available = $invitation && $invitation->usable();
        if ($available && !$request->user()) $request->session()->put('contact_invitation', $token);
        if (!$available && $request->session()->get('contact_invitation') === $token) $request->session()->forget('contact_invitation');
        return response()->view('social.invitation', ['invitation' => $available ? $invitation : null, 'token' => $token], $available ? 200 : 410);
    }

    public function accept(Request $request, string $token, ContactInvitations $invitations)
    {
        $invitations->accept($token, $request->user());
        $request->session()->forget('contact_invitation');
        return redirect()->route('contacts.index')->with('status', 'Invitation acceptée. Vous pouvez maintenant vous envoyer des recommandations.');
    }

    public function dismiss(Request $request)
    {
        $request->session()->forget('contact_invitation');
        return redirect()->route('contacts.index')->with('status', 'Invitation masquée. Aucune relation n’a été créée.');
    }

    public function revoke(Request $request, ContactInvitation $invitation)
    {
        abort_unless($invitation->sender_id === $request->user()->id, 404);
        // Lock against a simultaneous acceptance; an existing relation is never removed here.
        \Illuminate\Support\Facades\DB::transaction(function () use ($invitation) {
            $locked = ContactInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (!$locked->accepted_at && !$locked->revoked_at) $locked->update(['revoked_at' => now()]);
        });
        return back()->with('status', 'Invitation révoquée ou déjà utilisée.');
    }
}
