<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Contacts et recommandations') · VOD Finder</title>@include('partials.app-head')</head>
<body class="bg-slate-900 text-slate-100"><div class="vod-shell" id="main-content">@include('partials.main-navigation')
<nav class="vod-social-tabs" aria-label="Mon compte"><a href="{{ route('account.edit') }}">Mon compte</a><a href="{{ route('contacts.index') }}">Mes contacts</a><a href="{{ route('recommendations.index') }}">Recommandations</a></nav>
@if(session('status'))<p role="status" class="vod-social-notice">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="vod-social-notice">{{ $errors->first() }}</p>@endif
@yield('content')</div>@include('partials.footer')
</body></html>
