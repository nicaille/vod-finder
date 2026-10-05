@extends('social.layout')
@section('title',$recommendation->content['title'])
@section('content')<div class="vod-recommendation-detail">@include('social.card')<p class="vod-social-message">{{ $recommendation->message }}</p><a class="vod-social-button" href="{{ route('content.show',[$recommendation->type,$recommendation->tmdb_id]) }}">Ouvrir la fiche {{ $recommendation->type==='person'?'de la personne':'du contenu' }}</a></div>@endsection
