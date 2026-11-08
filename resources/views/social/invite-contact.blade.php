@push('styles')<link rel="stylesheet" href="/assets/contact-invitations.css">@endpush
<section class="vod-social-panel vod-contact-invite" data-contact-invite>
<h2>Inviter un proche</h2>
<p>Choisis un contact ou saisis ses coordonnées. Il pourra créer son compte, puis accepter votre mise en relation.</p>
<button type="button" data-contact-picker hidden>Choisir dans mon répertoire</button>
<p class="vod-social-muted" data-contact-picker-help>Tu peux saisir les coordonnées ci-dessous ou partager un lien. Le répertoire n’est jamais importé.</p>
<label data-contact-email-choice hidden>Adresses du contact<select data-contact-emails></select></label>
<label data-contact-phone-choice hidden>Numéros du contact<select data-contact-phones></select></label>
<div class="vod-social-columns">
<div>
<form method="POST" action="{{ route('contact-invitations.store') }}" data-invite-email>
@csrf<input type="hidden" name="channel" value="email">
<label>Adresse e-mail<input name="email" type="email" autocomplete="off" maxlength="255" required data-invite-email-input></label>
<button type="submit">Envoyer l’invitation par e-mail</button>
</form>
@if(!auth()->user()->hasVerifiedEmail())<p class="vod-social-muted">L’envoi par e-mail demande une adresse d’expéditeur confirmée dans Mon compte. Le lien et le SMS restent disponibles.</p>@endif
</div>
<div>
<label>Numéro pour le SMS (facultatif)<input type="tel" autocomplete="off" maxlength="40" data-invite-phone></label>
<p class="vod-social-muted">Le numéro reste sur cet appareil. Tu confirmeras l’envoi dans ton application SMS.</p>
<form method="POST" action="{{ route('contact-invitations.store') }}" data-invite-link>
@csrf<input type="hidden" name="channel" value="link"><button type="submit">Créer un lien d’invitation</button>
</form>
</div>
</div>
<div data-invite-result @if(!session('invitation_link')) hidden @endif>
<label>Lien privé · valable 7 jours, une seule acceptation<input type="text" readonly data-invite-url value="{{ session('invitation_link') }}" autocomplete="off"></label>
<div class="vod-social-actions">
<button type="button" data-invite-copy>Copier le lien</button>
<button type="button" data-invite-share hidden>Partager…</button>
<a class="vod-social-button" data-invite-sms hidden>Préparer un SMS</a>
</div>
<p class="vod-social-muted">L’application de partage ou de SMS te laisse choisir le destinataire et confirmer l’envoi. Crée un lien différent pour chaque personne.</p>
<p><a class="vod-social-button" href="{{ route('contacts.index') }}#mes-invitations">Actualiser et gérer mes invitations</a></p>
</div>
<p role="status" aria-live="polite" data-invite-status></p>
<noscript><p>Crée un lien avec le bouton ci-dessus, puis copie-le dans ton SMS ou ton application de messagerie.</p></noscript>
</section>
@if($invitations->isNotEmpty())
<section class="vod-social-panel" id="mes-invitations"><h2>Mes invitations récentes</h2>
@foreach($invitations as $invitation)
<article class="vod-contact-row"><div><strong>{{ $invitation->label() }}</strong><p class="vod-social-muted">Créée le {{ $invitation->created_at->timezone('Europe/Paris')->format('d/m/Y à H:i') }} · expire le {{ $invitation->expires_at->timezone('Europe/Paris')->format('d/m/Y à H:i') }}</p></div>
@if($invitation->usable())<form method="POST" action="{{ route('contact-invitations.revoke', $invitation) }}">@csrf @method('DELETE')<button>Révoquer cette invitation</button></form>@endif
</article>
@endforeach
{{ $invitations->withQueryString()->fragment('mes-invitations')->links() }}
</section>
@endif
<script src="/assets/contact-invitations.js" defer></script>
