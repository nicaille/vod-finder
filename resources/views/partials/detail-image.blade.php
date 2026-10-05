@php $detailImage=\App\Support\TmdbImage::detail($details, $person ?? false); @endphp
<div class="{{ ($person ?? false) ? 'vod-detail-portrait' : 'vod-detail-hero' }}" data-detail-image-frame>
@if($detailImage)
<img class="vod-detail-image" src="{{ $detailImage['original'] }}" alt="{{ $details['title']??$details['name']??'' }}"
     data-detail-image data-image-width="{{ $detailImage['width'] }}" data-image-height="{{ $detailImage['height'] }}"
     data-image-sources="{{ json_encode($detailImage['sources'],JSON_UNESCAPED_SLASHES) }}" decoding="async" loading="lazy">
@else
<span class="vod-social-muted">Aucune image disponible</span>
@endif
</div>
