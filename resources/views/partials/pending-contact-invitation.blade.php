@auth
@if(!request()->routeIs('contact-invitations.*') && ($pendingInvitationUrl = \App\Services\ContactInvitations::continuation(request(), false)))
<div class="vod-social-notice"><a href="{{ $pendingInvitationUrl }}">Une invitation t’attend : reprendre la mise en relation</a><form class="vod-social-actions" method="POST" action="{{ route('contact-invitations.dismiss') }}">@csrf<button class="vod-social-button">Masquer cette invitation</button></form></div>
@endif
@endauth
