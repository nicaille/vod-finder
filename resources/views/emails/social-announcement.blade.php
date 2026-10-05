<h1>{{ $event->label() }}</h1>
@if($event->recommendation)
@php($content=$event->recommendation->content)
<a href="{{ $event->url() }}">@if($content['image']??null)<img src="{{ $content['image'] }}" width="180" alt="{{ $content['title'] }}">@endif<h2>{{ $content['title'] }}</h2></a><p>{{ $content['description']??'' }}</p><p>{{ $event->recommendation->message }}</p><p>Reçue le {{ $event->created_at->timezone('Europe/Paris')->format('d/m/Y à H:i') }}</p>
@foreach($content['providers']??[] as $provider)<p>{{ $provider['name']??$provider['slug']??'' }} · {{ $provider['access']??'' }}</p>@endforeach
@endif
<p><a href="{{ $event->url() }}">Ouvrir dans VOD Finder</a></p><p><a href="{{ route('account.edit') }}">Gérer mes notifications</a></p>
