@extends('social.layout')
@section('title','Envoyer une recommandation')
@section('content')<section class="vod-social-panel"><h1>Recommander {{ $content['title'] }}</h1><p>{{ ['movie'=>'Film','tv'=>'Série','person'=>'Personne'][$data['type']] }} {{ $content['year']??'' }}</p>
@if($connections->isEmpty())<p>Ajoute un contact et attends son acceptation pour envoyer une recommandation.</p><a href="{{ route('contacts.index') }}">Trouver un contact</a>
@else<form method="POST" action="{{ route('recommendations.store') }}">@csrf<input type="hidden" name="type" value="{{ $data['type'] }}"><input type="hidden" name="tmdb_id" value="{{ $data['id'] }}">
<label>Destinataire<select name="recipient_id" required>@foreach($connections as $connection)@php $other=$connection->other(auth()->id()); @endphp<option value="{{ $other->id }}">{{ $other->socialName() }}</option>@endforeach</select></label>
<label>Ton message (facultatif)<textarea name="message" maxlength="1000" rows="4" placeholder="Pourquoi lui recommandes-tu cette découverte ?">{{ old('message') }}</textarea></label><button>Envoyer la recommandation</button></form>@endif</section>@endsection
