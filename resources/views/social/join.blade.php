@extends('social.layout')
@section('content')<section class="vod-social-panel"><h1>Rejoindre {{ $target->socialName() }}</h1><p>Vous pourrez vous recommander des contenus une fois la demande acceptée.</p><form method="POST" action="{{ route('contacts.invite') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button>Envoyer une demande de contact</button></form></section>@endsection
