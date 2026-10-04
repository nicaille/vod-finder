<nav aria-label="Navigation principale" class="flex flex-wrap justify-between items-center gap-2 mb-6 border-b border-slate-700 pb-1">
    <div class="flex flex-wrap">
        <a href="{{ route('search.index') }}"
           @if(request()->routeIs('search.index')) aria-current="page" @endif
           class="px-4 py-2 text-sm font-semibold
                  {{ request()->routeIs('search.index') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
            On regarde quoi ?
        </a>

        @auth
            <a href="{{ route('watchlist.index') }}"
               @if(request()->routeIs('watchlist.index')) aria-current="page" @endif
               class="px-4 py-2 text-sm font-semibold
                      {{ request()->routeIs('watchlist.index') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
                Playlist
            </a>
            <a href="{{ route('account.edit') }}"
               @if(request()->routeIs('account.*')) aria-current="page" @endif
               class="px-4 py-2 text-sm font-semibold
                      {{ request()->routeIs('account.*') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
                Mon compte
            </a>

            <a href="{{ route('lists.index') }}"
               @if(request()->routeIs('lists.index')) aria-current="page" @endif
               class="px-4 py-2 text-sm font-semibold
                      {{ request()->routeIs('lists.index') ? 'border-b-2 border-indigo-400 text-indigo-300' : 'text-slate-400 hover:text-slate-200' }}">
                ❤️ Mes listes
            </a>
        @endauth
    </div>

    <div>
        @guest
            <a href="{{ route('login') }}"
               class="px-3 py-1 text-xs font-semibold rounded border border-slate-600 text-slate-300 hover:bg-slate-700">
                Se connecter
            </a>
        @else
            <form action="{{ route('logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit"
                        class="px-3 py-1 text-xs font-semibold rounded border border-slate-600 text-slate-300 hover:bg-slate-700">
                    Se déconnecter
                </button>
            </form>
        @endguest
    </div>
</nav>
