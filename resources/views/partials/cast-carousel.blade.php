@php
    $cast = collect($details['credits']['cast'] ?? [])
        ->filter(fn ($person) => !empty($person['id']) && !empty($person['name']))
        ->unique('id')
        ->values();
@endphp
@if($cast->isNotEmpty())
<section class="vod-cast-section" x-data aria-labelledby="vod-cast-title">
    <div class="vod-cast-heading">
        <h2 id="vod-cast-title">Casting <span class="vod-cast-count">({{ $cast->count() }})</span></h2>
        <div class="vod-cast-navigation" aria-label="Navigation du casting">
            <button type="button" class="vod-cast-arrow" x-on:click="$refs.track.scrollBy({left: -$refs.track.clientWidth, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'})" aria-label="Acteurs précédents">‹</button>
            <button type="button" class="vod-cast-arrow" x-on:click="$refs.track.scrollBy({left: $refs.track.clientWidth, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'})" aria-label="Acteurs suivants">›</button>
        </div>
    </div>
    <div class="vod-cast-track" x-ref="track" tabindex="0" aria-label="Liste des acteurs, défilement horizontal">
        @foreach($cast as $person)
            <a class="vod-cast-card" href="{{ route('content.show', ['person', $person['id']]) }}">
                @if(!empty($person['profile_path']))
                    <img class="vod-cast-photo" src="https://image.tmdb.org/t/p/w185{{ $person['profile_path'] }}"
                         alt="Portrait de {{ $person['name'] }}" loading="lazy" decoding="async" width="185" height="278">
                @else
                    <span class="vod-cast-photo vod-cast-placeholder" aria-hidden="true">👤</span>
                @endif
                <span class="vod-cast-name">{{ $person['name'] }}</span>
                @if(!empty($person['character']))
                    <span class="vod-cast-character">{{ $person['character'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</section>
<style>
.vod-cast-section{min-width:0;max-width:100%;margin:1.5rem 0}
.vod-cast-heading{display:flex;justify-content:space-between;align-items:center;gap:1rem}
.vod-cast-heading h2{margin:0 0 .75rem}
.vod-cast-count{font-size:.8em;opacity:.65;font-weight:400}
.vod-cast-navigation{display:flex;gap:.5rem}
.vod-cast-arrow{border:1px solid currentColor;background:transparent;color:inherit;border-radius:50%;width:2rem;height:2rem;cursor:pointer;font-size:1.4rem;line-height:1}
.vod-cast-track{display:flex;gap:1rem;overflow-x:auto;overscroll-behavior-inline:contain;scroll-snap-type:x mandatory;scrollbar-width:thin;padding:.25rem .1rem 1rem}
.vod-cast-card{display:flex;flex:0 0 140px;flex-direction:column;gap:.3rem;min-width:0;text-decoration:none;color:inherit;scroll-snap-align:start}
.vod-cast-photo{width:100%;aspect-ratio:2/3;object-fit:cover;border-radius:.65rem;background:rgba(128,128,128,.16)}
.vod-cast-placeholder{display:flex;align-items:center;justify-content:center;font-size:3rem}
.vod-cast-name{font-weight:650;line-height:1.3}
.vod-cast-character{font-size:.85rem;opacity:.7;line-height:1.3}
.vod-cast-card:hover .vod-cast-name,.vod-cast-card:focus-visible .vod-cast-name{text-decoration:underline}
@media(min-width:768px){.vod-cast-card{flex-basis:160px}}
@media(prefers-reduced-motion:reduce){.vod-cast-track{scroll-behavior:auto}}
</style>

@endif
