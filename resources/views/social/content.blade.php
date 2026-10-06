@extends('social.layout')
@section('title',$content['title'])
@section('content')<article class="vod-content-detail {{ $type==='person'?'':'vod-content-detail-media' }}">@include('partials.detail-image',['person'=>$type==='person'])<div><h1>{{ $content['title'] }}</h1><p>{{ ['movie'=>'Film','tv'=>'Série','person'=>'Personne'][$type] }} {{ $content['year'] }}</p><p>{{ implode(' · ',$content['genres']) }}</p><p class="vod-social-biography">{{ $content['description'] }}</p>
@if($type!=='person')@php $recommendation=(object)['type'=>$type]; @endphp@include('social.providers')@elseif($details['birthday']??null)<p>Naissance : {{ \Carbon\Carbon::parse($details['birthday'])->format('d/m/Y') }}</p>@endif
@if($type !== 'person')@include('partials.content-actions')@else @auth<a class="vod-action-pill" href="{{ route('recommendations.compose',['type'=>$type,'id'=>$id]) }}" aria-label="Recommander">@include('partials.action-icon',['icon'=>'forward'])<span class="vod-action-label">Recommander</span></a>@endauth @endif
@if($type==='person')<h2>Œuvres de sa filmographie</h2><div class="vod-social-actions">@foreach($works as $work)<a href="{{ route('content.show',[$work['media_type'],$work['id']]) }}">{{ $work['title']??$work['name'] }}</a>@endforeach</div>@else
@foreach(['Director' => 'Réalisation', 'Producer' => 'Production', 'Executive Producer' => 'Production exécutive'] as $job => $label)
@php $people = collect($details['credits']['crew'] ?? [])->where('job', $job)->unique('id'); @endphp
@if($people->isNotEmpty())<h2>{{ $label }}</h2><div class="vod-social-actions">@foreach($people as $person)@include('partials.person-search-link')@endforeach</div>@endif
@endforeach
<h2>Interprètes</h2><div class="vod-social-actions">@foreach(array_slice($details['credits']['cast']??[],0,12) as $person)@include('partials.person-search-link')@endforeach</div>@endif
</div></article>@endsection
