<span class="vod-card-poster">
@if(!empty($poster))
<img src="{{ $poster }}" @if($srcset = \App\Support\TmdbImage::posterSrcset($poster)) srcset="{{ $srcset }}" sizes="(max-width: 767px) calc((100vw - 44px) / 2), 260px" @endif alt="{{ $title ?? '' }}" loading="lazy" decoding="async">
@else<span class="vod-card-placeholder">Affiche indisponible</span>@endif
</span>
