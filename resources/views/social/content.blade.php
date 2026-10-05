@extends('social.layout')
@section('title',$content['title'])
@section('content')<article class="vod-content-detail {{ $type==='person'?'':'vod-content-detail-media' }}">@include('partials.detail-image',['person'=>$type==='person'])<div><h1>{{ $content['title'] }}</h1><p>{{ ['movie'=>'Film','tv'=>'Série','person'=>'Personne'][$type] }} {{ $content['year'] }}</p><p>{{ implode(' · ',$content['genres']) }}</p><p class="vod-social-biography">{{ $content['description'] }}</p>
@if($type!=='person')@php $recommendation=(object)['type'=>$type]; @endphp@include('social.providers')@elseif($details['birthday']??null)<p>Naissance : {{ \Carbon\Carbon::parse($details['birthday'])->format('d/m/Y') }}</p>@endif
@auth<a class="vod-social-button" href="{{ route('recommendations.compose',['type'=>$type,'id'=>$id]) }}">Recommander à un contact</a>@endauth
@if($type==='person')<h2>Œuvres de sa filmographie</h2><div class="vod-social-actions">@foreach($works as $work)<a href="{{ route('content.show',[$work['media_type'],$work['id']]) }}">{{ $work['title']??$work['name'] }}</a>@endforeach</div>@else<h2>Interprètes</h2><div class="vod-social-actions">@foreach(array_slice($details['credits']['cast']??[],0,12) as $person)<a href="{{ route('content.show',['person',$person['id']]) }}">{{ $person['name'] }}</a>@endforeach</div>@endif
</div></article>@endsection
