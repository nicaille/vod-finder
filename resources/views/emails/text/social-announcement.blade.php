{!! $event->label() !!}

@if($event->recommendation)
@php($content = $event->recommendation->content)
{!! $content['title'] !!}
{!! $content['description'] ?? '' !!}
{!! $event->recommendation->message !!}

Reçue le {!! $event->created_at->timezone('Europe/Paris')->format('d/m/Y à H:i') !!}
@foreach($content['providers'] ?? [] as $provider)
{!! $provider['name'] ?? $provider['slug'] ?? '' !!} · {!! $provider['access'] ?? '' !!}
@endforeach
@endif

Ouvrir dans VOD Finder : {!! $event->url() !!}

@include('emails.text.footer', ['showPreferences' => true, 'notificationReason' => 'Tu reçois cet e-mail parce que tu as activé les notifications par e-mail pour les contacts et recommandations dans ton compte.'])
