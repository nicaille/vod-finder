<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title') · VOD Finder</title>@include('partials.app-head')</head><body><div class="vod-shell" id="main-content">@include('partials.main-navigation')
@if(session('status'))<p role="status" class="vod-social-notice">{{ session('status') }}</p>@endif
@yield('content')</div>@include('partials.footer')
</body></html>
