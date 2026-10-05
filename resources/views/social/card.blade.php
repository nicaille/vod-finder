@php $content=$recommendation->content; @endphp
<article class="vod-recommendation-card">
<a href="{{ route('recommendations.show',$recommendation) }}">@if($content['image']??null)<img src="{{ $content['image'] }}" alt="{{ $content['title'] }}" loading="lazy">@else<div class="vod-social-poster">Pas d’image disponible</div>@endif<h2>{{ $content['title'] }}</h2></a>
<p>{{ ['movie'=>'Film','tv'=>'Série','person'=>'Personne'][$recommendation->type] }} {{ $content['year']??'' }} @if(!$recommendation->read_at)<strong class="vod-badge">Nouveau</strong>@endif</p>
<p>De {{ $recommendation->sender->socialName() }}</p><time datetime="{{ $recommendation->created_at->toIso8601String() }}">Reçue le {{ $recommendation->created_at->timezone('Europe/Paris')->format('d/m/Y à H:i') }}</time>
<p class="vod-recommendation-description">{{ $content['description']??'' }}</p>
@include('social.providers')
<div class="vod-social-actions">@foreach(($recommendation->archived_at?['restore'=>'Restaurer']:['read'=>'Marquer lue','unread'=>'Marquer non lue','archive'=>'Archiver'])+['delete'=>'Supprimer'] as $action=>$label)<form method="POST" action="{{ route('recommendations.update',$recommendation) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="{{ $action }}"><button>{{ $label }}</button></form>@endforeach</div>
</article>
