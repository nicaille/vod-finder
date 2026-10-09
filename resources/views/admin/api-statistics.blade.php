@extends('pages.layout')
@section('title', 'Statistiques des API')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration</span><h1>Statistiques des API</h1><p>Nombre de requêtes et d’échecs par API et par période. Dates et heures de Paris.</p></section>
@include('partials.admin-navigation')
@if($errors->any())<div class="vod-social-notice" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="vod-social-panel">
    <form method="GET" class="vod-api-filters">
        <label>Du<input type="datetime-local" name="from" value="{{ $from->format('Y-m-d\TH:i') }}" required></label>
        <label>Au (exclu)<input type="datetime-local" name="to" value="{{ $to->format('Y-m-d\TH:i') }}" required></label>
        <label>API<select name="service"><option value="all" @selected($service === 'all')>Toutes les API</option>@foreach($services as $name)<option value="{{ $name }}" @selected($service === $name)>{{ $name }}</option>@endforeach</select></label>
        <label>Regrouper par<select name="interval"><option value="hour" @selected($interval === 'hour')>Heure</option><option value="day" @selected($interval === 'day')>Jour</option></select></label>
        <button type="submit">Afficher</button>
    </form>
    <p class="vod-social-muted">Période maximale : 90 jours. Appels HTTP réels, reprises incluses ; les lectures du cache ne sont pas comptées. Un échec correspond à une erreur de connexion ou à un statut HTTP ≥ 400.</p>
    <div class="vod-admin-stats"><section><strong>{{ number_format(array_sum(array_column($report['totals'], 'requests')), 0, ',', ' ') }}</strong><span>Requêtes sur la période</span></section><section><strong>{{ number_format(array_sum(array_column($report['totals'], 'failures')), 0, ',', ' ') }}</strong><span>Échecs sur la période</span></section></div>
    <div class="vod-table-scroll"><table class="vod-admin-table"><thead><tr><th>API</th><th>Requêtes</th><th>Échecs</th><th>Taux d’échec</th></tr></thead><tbody>@foreach($report['totals'] as $name => $total)<tr><th>{{ $name }}</th><td>{{ number_format($total['requests'], 0, ',', ' ') }}</td><td>{{ number_format($total['failures'], 0, ',', ' ') }}</td><td>{{ $total['requests'] ? number_format(100 * $total['failures'] / $total['requests'], 1, ',', ' ') : '0' }} %</td></tr>@endforeach</tbody></table></div>
</section>
<section class="vod-social-panel">
    <h2>Évolution des appels</h2>
    <label for="api-chart-metric">Afficher</label> <select id="api-chart-metric"><option value="requests">Requêtes</option><option value="failures">Échecs</option><option value="rate_limited">Limitations (HTTP 429)</option></select>
    <p class="vod-social-muted">Survole ou touche le diagramme pour consulter les valeurs. Clique sur une API dans la légende pour la masquer ou l’afficher.</p>
    <div class="vod-api-chart"><canvas id="api-statistics-chart" role="img" aria-label="Requêtes API par période">Les totaux sont disponibles dans le tableau ci-dessus.</canvas></div>
    @if(!array_sum(array_column($report['totals'], 'requests')))<p role="status">Aucun appel enregistré sur cette période.</p>@endif
    <p class="vod-social-muted">@if($report['firstRecordedAt'])Historique horodaté depuis le {{ \Carbon\CarbonImmutable::parse($report['firstRecordedAt'], 'UTC')->timezone('Europe/Paris')->format('d/m/Y à H:i') }}.@else L’historique commence avec les prochains appels API.@endif Les anciens compteurs cumulés restent consultables dans <a href="{{ route('admin.health') }}">État du service</a>.</p>
</section>
@include('admin.partials.cache-statistics')
@include('admin.partials.api-limits')
<script type="application/json" id="api-statistics-data">{!! json_encode(\Illuminate\Support\Arr::only($report, ['labels', 'datasets']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@vite('resources/js/admin-api-statistics.js')
@endsection
