<section class="vod-social-panel">
    <h2>Limites et quotas des API</h2>
    @foreach($streamingBudgets as $streamingBudget)
    @if($service === 'all' || $service === $streamingBudget['service'])
    <section aria-labelledby="streaming-budget-{{ $loop->index }}" data-streaming-budget>
        <h3 id="streaming-budget-{{ $loop->index }}">{{ $streamingBudget['label'] }} · {{ number_format($streamingBudget['limit'], 0, ',', ' ') }} requêtes par mois</h3>
        @unless($streamingBudget['configured'])<p>Accès non configuré : aucune clé chargée pour ce compte.</p>@endunless
        <p>Forfait gratuit confirmé. <strong>{{ number_format($streamingBudget['used'], 0, ',', ' ') }} appels observés</strong> · {{ number_format($streamingBudget['percent'], 1, ',', ' ') }} % du budget indicatif.</p>
        <progress max="{{ $streamingBudget['limit'] }}" value="{{ min($streamingBudget['used'], $streamingBudget['limit']) }}" aria-label="Consommation observée du budget mensuel {{ $streamingBudget['label'] }}"></progress>
        <p role="status">{{ $streamingBudget['state'] }}</p>
        <p class="vod-social-muted">Du {{ $streamingBudget['start']->format('d/m/Y') }} au {{ $streamingBudget['end']->subDay()->format('d/m/Y') }}, heures de Paris, indépendamment des filtres du graphique.
        @if($streamingBudget['calendar_cycle'])Remise à zéro annoncée le 1er du mois pour le compte direct ; l’heure exacte dépend du fournisseur.
        @else Estimation sur le mois civil : le cycle de facturation RapidAPI peut commencer à une autre date.@endif
        Les appels effectués ailleurs ou avant l’activation du suivi ne sont pas connus ; les échecs et reprises observés sont inclus. Le compteur et la date de renouvellement du fournisseur font foi. Aucun blocage automatique n’est déclenché par cette estimation.</p>
    </section>
    @endif
    @endforeach
    <p class="vod-social-muted">{{ number_format(array_sum(array_column($report['totals'], 'rate_limited')), 0, ',', ' ') }} réponses HTTP 429 observées sur la période sélectionnée depuis l’activation de ce suivi. Les anciennes périodes ne sont pas reconstituées. La limite exacte dépend de l’API, du forfait et parfois de l’adresse IP partagée du serveur ; ce compteur ne remplace pas le suivi de consommation du fournisseur.</p>
    <div class="vod-table-scroll"><table class="vod-admin-table"><thead><tr><th>API</th><th>Repère public · vérifié le 10/10/2026</th><th>Réponses 429 sur la période</th></tr></thead><tbody>
    @foreach($report['totals'] as $name => $total)<tr><th>{{ $name }}</th><td>@switch($name)
        @case('TMDb')<a href="https://developer.themoviedb.org/docs/rate-limiting" target="_blank" rel="noopener">Environ 40 requêtes/seconde</a>, limite de protection variable. L’ancienne limite de 40 requêtes / 10 secondes est supprimée depuis 2019.@break
        @case('TVmaze')<a href="https://www.tvmaze.com/api#rate-limiting" target="_blank" rel="noopener">Au moins 20 appels / 10 secondes par IP</a>. Une limite temporairement plus stricte reste possible.@break
        @case('Streaming Availability')<a href="https://rapidapi.com/movie-of-the-night-movie-of-the-night-default/api/streaming-availability/pricing" target="_blank" rel="noopener">Forfait gratuit confirmé : 1 000 requêtes/mois</a>. La date de renouvellement est celle de la souscription RapidAPI.@break
        @case('Streaming Availability Direct')Accès direct v4 : 1 000 requêtes/mois, remise à zéro le 1er du mois. Compte et consommation distincts de RapidAPI.@break
        @case('Brevo')<a href="https://developers.brevo.com/docs/api-limits" target="_blank" rel="noopener">POST /v3/smtp/email : 1 000 requêtes/seconde, 3 600 000/heure au niveau général</a>. Quota d’envoi séparé : <a href="https://help.brevo.com/hc/en-us/articles/208580669-About-Brevo-s-pricing-plans" target="_blank" rel="noopener">300 e-mails/jour sur l’offre gratuite</a>, selon le forfait sur une offre payante.@break
    @endswitch</td><td>{{ number_format($total['rate_limited'], 0, ',', ' ') }}</td></tr>@endforeach
    </tbody></table></div>
    <h3>Derniers quotas annoncés dans les réponses</h3>
    <p class="vod-social-muted">Dernière observation disponible, indépendamment de la période sélectionnée. Seuls les en-têtes numériques de limitation sont conservés. Une absence d’en-tête ne signifie pas un quota illimité ; la fenêtre et l’unité de réinitialisation sont celles du fournisseur.</p>
    @forelse($quotas as $quota)<details><summary>{{ $quota->service }} · {{ $quota->quota_observed_at->timezone('Europe/Paris')->format('d/m/Y H:i') }}</summary><dl class="vod-admin-profile">@foreach($quota->quota_headers ?? [] as $name => $value)<dt>{{ $name }}</dt><dd>{{ $value }}</dd>@endforeach</dl></details>@empty<p>Aucun en-tête de quota observé pour le moment.</p>@endforelse
</section>
