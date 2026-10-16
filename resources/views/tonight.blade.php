<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>On regarde quoi ce soir ? · VOD Finder</title>
    @include('partials.app-head')
    <script src="/assets/tonight.js" defer></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
<div class="vod-shell" id="main-content">
    @include('partials.main-navigation')
    <section class="vod-intro"><span class="vod-eyebrow">Une idée pour maintenant</span><h1>On regarde quoi ce soir&nbsp;?</h1>
        <p>Choisis ton temps et ton envie. On cherche parmi tes abonnements actifs en France, en excluant les contenus marqués « déjà vus ».</p>
    </section>
    <form method="GET" action="{{ route('tonight.index') }}" class="vod-social-panel vod-tonight-form" data-tonight-form>
        <input type="hidden" name="suggest" value="1">
        <div class="vod-filter-bar">
            <div class="vod-tonight-field"><label for="tonight-minutes">Temps disponible</label><select id="tonight-minutes" name="minutes">
                @foreach(\App\Support\TonightPreferences::DURATIONS as $minutes)<option value="{{ $minutes }}" @selected($preferences['minutes'] === $minutes)>{{ $minutes }} minutes maximum</option>@endforeach
            </select></div>
            <div class="vod-tonight-field"><label for="tonight-type">Format</label><select id="tonight-type" name="type">
                @foreach(['all'=>'Film / Série', 'movie'=>'Film', 'tv'=>'Un épisode de série'] as $value=>$label)<option value="{{ $value }}" @selected($preferences['type'] === $value)>{{ $label }}</option>@endforeach
            </select></div>
            <div class="vod-tonight-field"><label for="tonight-genre">Genre</label><select id="tonight-genre" name="genre">
                @foreach(\App\Support\TonightPreferences::GENRES as $value=>$label)<option value="{{ $value }}" @selected($preferences['genre'] === $value)>{{ $label }}</option>@endforeach
            </select></div>
            <div class="vod-tonight-field"><label for="tonight-mood">Ton envie</label><select id="tonight-mood" name="mood">
                @foreach(\App\Support\TonightPreferences::MOODS as $value=>$label)<option value="{{ $value }}" @selected($preferences['mood'] === $value)>{{ $label }}</option>@endforeach
            </select></div>
        </div>
        @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
        <button class="vod-tonight-submit" type="submit">Trouver une idée</button>
        <p class="text-sm text-slate-400 mt-3">Pour une série, la durée est indicative, par épisode. L’envie correspond à une sélection de genres ; elle ne constitue pas une classification par âge.</p>
        <p data-tonight-loading role="status" aria-live="polite" hidden>Recherche de suggestions et vérification de tes plateformes…</p>
    </form>
    <section aria-labelledby="tonight-results-title" data-tonight-state="{{ $suggestions['state'] }}">
        <h2 id="tonight-results-title" class="text-xl font-semibold mt-8 mb-4">Tes idées pour ce soir</h2>
        @if($suggestions['state'] === 'initial')<p>Règle tes critères puis lance la sélection.</p>
        @elseif($suggestions['state'] === 'no_platforms')<p>Ajoute au moins un abonnement actif dans <a class="underline" href="{{ route('account.edit') }}">Mon compte</a> pour obtenir des suggestions incluses.</p>
        @elseif($suggestions['state'] === 'unavailable')<p role="status">Impossible de vérifier les suggestions pour le moment. Réessaie dans un instant ; aucune offre non vérifiée n’est affichée.</p>
        @elseif($suggestions['state'] === 'empty')<p>Aucun contenu confirmé dans la sélection consultée pour ces critères. Essaie une durée plus longue, un autre genre ou une autre envie.</p>
        @else
            @if($suggestions['state'] === 'partial')<p role="status">Une partie des vérifications est indisponible. Voici les offres que nous avons pu confirmer.</p>@endif
            <p class="text-sm text-slate-300 mb-4">{{ count($suggestions['results']) }} idée(s) incluse(s) dans tes abonnements, sans location ni achat. Cette sélection n’est pas exhaustive.</p>
        @endif
        <div class="vod-media-grid" data-tonight-results>
        @foreach($suggestions['results'] as $item)
            <article class="vod-media-card">
                <a class="vod-card-open" href="{{ route('content.show', ['type'=>$item['type'], 'id'=>$item['id']]) }}" aria-label="Voir la fiche de {{ $item['title'] }}">
                    @include('partials.media-poster', ['poster'=>$item['poster'], 'title'=>$item['title']])
                </a>
                <div class="vod-card-body p-3 space-y-2">
                    <p class="vod-card-kind">{{ $item['type'] === 'tv' ? 'Série' : 'Film' }} · {{ $item['year'] }}</p>
                    <h3 class="font-semibold"><a href="{{ route('content.show', ['type'=>$item['type'], 'id'=>$item['id']]) }}">{{ $item['title'] }}</a></h3>
                    <p class="vod-tonight-duration">{{ $item['runtime'] }} min{{ $item['type'] === 'tv' ? ' / épisode environ' : '' }} · dans tes {{ $preferences['minutes'] }} min</p>
                    <p class="text-sm text-slate-300">{{ \Illuminate\Support\Str::limit($item['overview'], 150) }}</p>
                    <div class="vod-tonight-providers" aria-label="Inclus dans tes abonnements">
                    @foreach($item['providers'] as $provider)<span>{{ $provider['name'] ?? $provider['slug'] }}@if(!empty($provider['via'])) · via {{ collect(require app_path('Support/Platforms.php'))->firstWhere('slug', $provider['via'])['name'] ?? $provider['via'] }}@endif</span>@endforeach
                    </div>
                </div>
            </article>
        @endforeach
        </div>
    </section>
</div>
@include('partials.footer')
</body></html>
