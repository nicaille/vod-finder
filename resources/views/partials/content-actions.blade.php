@auth
    <div class="vod-detail-actions" data-content-actions data-id="{{ $details['id'] }}" data-type="{{ $type }}">

        @if($isTv)
            @php
                $seriesFollow = auth()->user()->seriesFollows()->whereHas('series', fn ($query) => $query->where('tmdb_id', $details['id']))->first();
            @endphp
            @if($seriesFollow)
                <button type="button" class="vod-action-pill is-active" data-action="follow" aria-pressed="true" aria-label="Série suivie" title="Série suivie">@include('partials.action-icon', ['icon' => 'alarm'])<span class="vod-action-label">Série suivie</span></button>
            @else
                <form action="{{ route('series.store') }}" method="POST" data-series-follow-form>
                    @csrf
                    <input type="hidden" name="tmdb_id" value="{{ $details['id'] }}">
                    <button type="submit" class="vod-action-pill" data-action="follow" aria-pressed="false" aria-label="Suivre la série" title="Suivre la série">@include('partials.action-icon', ['icon' => 'alarm'])<span class="vod-action-label">Suivre la série</span></button>
                </form>
            @endif
        @endif

        {{-- Bouton coup de cœur --}}
        @php
            $favActive = !empty($isFavorite);
        @endphp
        <button type="button" data-favorite-btn data-action="favorite" class="vod-action-pill {{ $favActive ? 'is-active' : '' }}" aria-pressed="{{ $favActive ? 'true' : 'false' }}" aria-label="{{ $favActive ? 'Retirer des coups de cœur' : 'Coup de cœur' }}" title="{{ $favActive ? 'Retirer des coups de cœur' : 'Coup de cœur' }}">
            @include('partials.action-icon', ['icon' => 'heart'])<span class="vod-action-label">Coup de cœur</span>
        </button>

        {{-- Bouton "Ajouter à une liste" + panneau --}}
        <div class="relative" data-add-to-list-wrapper>
            <button type="button" data-open-list-menu data-action="list" class="vod-action-pill {{ !empty($inUserList) ? 'is-active' : '' }}" aria-expanded="false" aria-label="{{ !empty($inUserList) ? 'Déjà dans une liste' : 'Ajouter à une liste' }}" title="{{ !empty($inUserList) ? 'Déjà dans une liste' : 'Ajouter à une liste' }}">
                @include('partials.action-icon', ['icon' => 'list'])<span class="vod-action-label">{{ !empty($inUserList) ? 'Dans une liste' : 'Ajouter à une liste' }}</span>
            </button>

            <div class="absolute right-0 mt-2 w-64 bg-slate-800 border border-slate-700 rounded-lg shadow-lg text-xs hidden"
                 data-list-panel>
                {{-- Listes existantes --}}
                @if(!empty($userLists) && count($userLists))
                    <div class="px-3 py-2 border-b border-slate-700">
                        <div class="text-[11px] text-slate-300 mb-1">
                            Ajouter à une liste existante :
                        </div>
                        @foreach($userLists as $list)
                            <button type="button"
                                    class="w-full text-left px-2 py-1.5 rounded hover:bg-slate-700"
                                    data-add-to-list
                                    data-list-id="{{ $list->id }}">
                                {{ $list->name }}
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="px-3 py-2 border-b border-slate-700 text-[11px] text-slate-300">
                        Vous n'avez encore aucune liste.
                    </div>
                @endif

                {{-- Création d'une nouvelle liste --}}
                <div class="px-3 py-2" data-create-list-panel>
                    <div class="text-[11px] text-slate-300 mb-1">
                        Créer une nouvelle liste :
                    </div>
                    <form data-create-list-form class="space-y-2">
                        <input type="text"
                               name="name"
                               class="w-full bg-slate-900 border border-slate-600 rounded px-2 py-1 text-xs"
                               placeholder="Nom de la liste"
                               required>

                        <label class="flex items-center gap-1 text-[11px] text-slate-300">
                            <input type="checkbox" name="is_public" class="rounded border-slate-500 text-xs">
                            <span>Liste publique</span>
                        </label>

                        <button type="submit"
                                class="w-full mt-1 px-2 py-1 rounded bg-indigo-600 hover:bg-indigo-500 text-[11px] font-semibold">
                            Créer la liste et ajouter ce titre
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <a class="vod-action-pill" data-action="recommend" aria-label="Recommander" title="Recommander" href="{{ route('recommendations.compose', ['type' => $type, 'id' => $details['id']]) }}">@include('partials.action-icon', ['icon' => 'forward'])<span class="vod-action-label">Recommander</span></a>
        <p role="status" class="vod-action-status" data-action-status hidden></p>
    </div>
@endauth
