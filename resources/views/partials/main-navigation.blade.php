<a href="#main-content" class="vod-skip">Aller au contenu</a>
<header class="vod-header @auth vod-auth-header @endauth">
    <div class="vod-brand">VOD <span>Finder</span></div>
    <div class="vod-header-actions"><button type="button" data-install-app>Installer</button></div>
</header>
<div class="vod-utility-links"><a href="{{ route('about.show') }}">À propos</a>@can('manage-site')<a href="{{ route('admin.index') }}">Administration</a>@endcan</div>
<p class="vod-install-help" data-install-help hidden></p>
<nav aria-label="Navigation principale" class="vod-navigation @guest vod-guest-nav @endguest">
    <div class="vod-nav-links">
        <a href="{{ route('search.index') }}"
           @if(request()->routeIs('search.index')) aria-current="page" @endif
           class="px-4 py-2 text-sm font-semibold
                  {{ request()->routeIs('search.index') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0" /></svg><span>On regarde quoi ?</span>
        </a>

        @auth
            <a href="{{ route('watchlist.index') }}"
               @if(request()->routeIs('watchlist.index')) aria-current="page" @endif
               class="px-4 py-2 text-sm font-semibold
                      {{ request()->routeIs('watchlist.index') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4zM10 8l6 4-6 4z" /></svg><span>Playlist</span>
            </a>
            <a href="{{ route('account.edit') }}"
               @if(request()->routeIs('account.*', 'contacts.*', 'recommendations.*')) aria-current="page" @endif
               class="px-4 py-2 text-sm font-semibold
                      {{ request()->routeIs('account.*', 'contacts.*', 'recommendations.*') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0" /></svg><span>Mon compte</span>
                @php $unreadSocial=auth()->user()->socialEvents()->whereNull('read_at')->count(); @endphp
                @if($unreadSocial)<span class="vod-badge" aria-label="{{ $unreadSocial }} notifications de contacts ou recommandations">{{ $unreadSocial }}</span>@endif
            </a>

            <a href="{{ route('lists.index') }}"
               @if(request()->routeIs('lists.index')) aria-current="page" @endif
               class="px-4 py-2 text-sm font-semibold
                      {{ request()->routeIs('lists.index') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21l-8-8a5 5 0 0 1 8-7 5 5 0 0 1 8 7z" /></svg><span>Mes listes</span>
            </a>
            <a href="{{ route('series.index') }}"
               @if(request()->routeIs('series.*')) aria-current="page" @endif
               class="px-4 py-2 text-sm font-semibold {{ request()->routeIs('series.*') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v16H4zM8 3v4M16 3v4M4 11h16M8 15h2M14 15h2" /></svg><span>Séries suivies</span>
                @php
                    $unreadEpisodeAlerts = auth()->user()->episodeAlerts()->announced()->whereNull('read_at')->count();
                @endphp
                @if($unreadEpisodeAlerts)
                    <span class="vod-badge" aria-label="{{ $unreadEpisodeAlerts }} alertes non lues">{{ $unreadEpisodeAlerts }}</span>
                @endif
            </a>
        @endauth
    </div>

    <div class="vod-account-actions">
        @guest
            <a href="{{ route('login') }}"
               class="px-3 py-1 text-xs font-semibold rounded border border-slate-600 text-slate-300 hover:bg-slate-700">
                Se connecter
            </a>
        @else
            <form action="{{ route('logout') }}" method="POST" class="inline vod-logout" data-logout>
                @csrf
                <button type="submit"
                        class="px-3 py-1 text-xs font-semibold rounded border border-slate-600 text-slate-300 hover:bg-slate-700">
                    Se déconnecter
                </button>
            </form>
        @endguest
    </div>
</nav>

@auth
@php $socialNotifications=auth()->user()->socialEvents()->whereNull('read_at')->with(['actor','recommendation'])->latest()->limit(5)->get(); @endphp
@if($socialNotifications->isNotEmpty())<details class="vod-social-inbox"><summary>{{ $unreadSocial }} notification(s) de contacts et recommandations</summary>
@foreach($socialNotifications as $notification)<a href="{{ $notification->url() }}">@if($notification->recommendation?->content['image']??null)<img src="{{ $notification->recommendation->content['image'] }}" alt="">@endif<span>{{ $notification->label() }}<time>{{ $notification->created_at->timezone('Europe/Paris')->format('d/m/Y à H:i') }}</time></span></a>@endforeach</details>@endif
@endauth
