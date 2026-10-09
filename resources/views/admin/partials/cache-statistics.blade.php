@php
    $totals = $cache['totals'];
    $number = fn ($value) => number_format($value, 0, ',', ' ');
    $ms = fn ($value) => $value === null ? '—' : number_format($value, 2, ',', ' ').' ms';
@endphp
<section class="vod-social-panel">
    <h2>Efficacité du cache</h2>
    <p class="vod-social-muted">Même période et filtre API que ci-dessus. Mesures des caches serveur de TMDb, TVmaze et Streaming Availability ; le cache du navigateur et les envois Brevo sont exclus.</p>
    <div class="vod-admin-stats">
        <section><strong>{{ $totals['hit_rate'] === null ? '—' : number_format($totals['hit_rate'], 1, ',', ' ').' %' }}</strong><span>Demandes servies depuis le cache</span></section>
        <section><strong>{{ $number($totals['avoided']) }}</strong><span>Appels HTTP évités · estimation</span></section>
        <section><strong>{{ number_format($totals['saved_us'] / 1000000, 1, ',', ' ') }} s</strong><span>Temps de chargement évité · estimation</span></section>
        <section><strong>{{ $number($totals['expired']) }}</strong><span>Rechargements après expiration</span></section>
    </div>
    <p class="vod-social-muted">{{ $number($totals['hits']) }} réponses du cache, {{ $number($totals['misses']) }} chargements nécessaires. Les économies estimées reprennent le nombre d’appels et la durée du dernier remplissage ; elles ne mesurent pas le temps gagné sur une page entière. {{ $number($totals['unknown_hits']) }} réponses réutilisées sans mesure antérieure sont exclues de ces estimations.</p>
    <h3>Cache et appels HTTP dans le temps</h3>
    <div class="vod-api-chart"><canvas id="cache-statistics-chart" role="img" aria-label="Réponses du cache, chargements nécessaires et appels HTTP par période">Consulte aussi les tableaux ci-dessous.</canvas></div>
    <p class="vod-social-muted">Un chargement peut produire zéro, un ou plusieurs appels HTTP : ces séries ne sont pas additionnables. Les appels HTTP incluent les reprises et, si toutes les API sont sélectionnées, les envois Brevo.</p>
    <details><summary>Détails par API et par usage</summary>
    <div class="vod-table-scroll"><table class="vod-admin-table"><thead><tr><th>API / usage</th><th>Demandes</th><th>Cache utilisé</th><th>Taux de succès</th><th>Absente / supprimée</th><th>Expirée</th><th>Échecs de chargement</th><th>Rechargements simultanés</th><th>Appels évités estimés</th></tr></thead><tbody>
    @forelse($cache['usages'] as $row)<tr><th>{{ $row['service'] }}<small>{{ \App\Services\CacheMonitor::USAGES[$row['usage']] ?? $row['usage'] }}</small></th><td>{{ $number($row['hits'] + $row['misses']) }}</td><td>{{ $number($row['hits']) }}</td><td>{{ number_format($row['hit_rate'], 1, ',', ' ') }} %</td><td>{{ $number($row['missing']) }}</td><td>{{ $number($row['expired']) }}</td><td>{{ $number($row['failures']) }}</td><td>{{ $number($row['concurrent']) }}</td><td>{{ $number($row['avoided']) }}</td></tr>@empty<tr><td colspan="9">Aucune mesure du cache sur cette période.</td></tr>@endforelse
    </tbody></table></div>
    <h3>Temps de réponse des opérations de cache</h3>
    <p class="vod-social-muted">Avec cache : lecture de la donnée. Sans cache : lecture et chargement, incluant l’API. Médiane et 95ᵉ percentile sont estimés par classes de durée ; « ≤ » indique la borne supérieure de la classe.</p>
    <div class="vod-table-scroll"><table class="vod-admin-table"><thead><tr><th>API / usage</th><th>Cache · moyenne</th><th>Cache · médiane</th><th>Cache · p95</th><th>Chargement · moyenne</th><th>Chargement · médiane</th><th>Chargement · p95</th></tr></thead><tbody>
    @forelse($cache['usages'] as $row)<tr><th>{{ $row['service'] }}<small>{{ \App\Services\CacheMonitor::USAGES[$row['usage']] ?? $row['usage'] }}</small></th><td>{{ $ms($row['hit_avg_ms']) }}</td><td>{{ $row['hit_p50_ms'] === null ? '—' : '≤ '.$ms($row['hit_p50_ms']) }}</td><td>{{ $row['hit_p95_ms'] === null ? '—' : '≤ '.$ms($row['hit_p95_ms']) }}</td><td>{{ $ms($row['miss_avg_ms']) }}</td><td>{{ $row['miss_p50_ms'] === null ? '—' : '≤ '.$ms($row['miss_p50_ms']) }}</td><td>{{ $row['miss_p95_ms'] === null ? '—' : '≤ '.$ms($row['miss_p95_ms']) }}</td></tr>@empty<tr><td colspan="7">Les durées apparaîtront avec les premières opérations mesurées.</td></tr>@endforelse
    </tbody></table></div>
    </details>
    <p class="vod-social-muted">Les expirations sont reconnues grâce à une métadonnée conservée 120 jours ; une donnée absente sans métadonnée est classée « absente / supprimée ». Les rechargements simultanés mesurent une collision sur la même clé, sans modifier les requêtes. Les données expirées ne sont actuellement pas servies en secours. L’historique commence après activation de ces mesures.</p>
</section>
@php
    $http = array_fill(0, count($report['labels']), 0);
    foreach ($report['datasets'] as $dataset) foreach ($dataset['requests'] as $index => $count) $http[$index] += $count;
    $cacheChart = ['labels' => $report['labels'], 'datasets' => [
        ['label' => 'Cache utilisé', 'data' => $cache['hits']],
        ['label' => 'Chargement nécessaire', 'data' => $cache['misses']],
        ['label' => 'Appels HTTP réels', 'data' => $http],
    ]];
@endphp
<script type="application/json" id="cache-statistics-data">{!! json_encode($cacheChart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
