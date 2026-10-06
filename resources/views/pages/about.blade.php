@extends('pages.layout')
@section('title', $page->title)
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Notre projet</span><h1>{{ $page->title }}</h1><p>Vos envies, vos plateformes, vos découvertes.</p></section>
<article class="vod-about-content">{!! $html !!}@include('partials.data-credits')</article>
@can('manage-site')<p class="vod-social-actions"><a class="vod-social-button" href="{{ route('admin.index') }}">Modifier cette page</a></p>@endcan
@endsection
