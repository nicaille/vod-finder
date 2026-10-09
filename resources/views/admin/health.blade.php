@extends('pages.layout')
@section('title', 'État du service')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration</span><h1>État du service</h1><p>Activité des API, tâches automatiques et livraisons de notifications. Horaires de Paris.</p></section>
@include('partials.admin-navigation')
<div class="vod-admin-stats">@foreach($counts as $label => $count)<section class="vod-social-panel"><strong>{{ $count }}</strong><span>{{ $label }}</span></section>@endforeach</div>
<section class="vod-social-panel"><h2>API externes</h2><p class="vod-social-muted">Appels HTTP observés depuis l’activation du suivi ; les lectures du cache ne produisent pas de nouvel appel.</p>
<div class="vod-table-scroll"><table class="vod-admin-table"><thead><tr><th>Source</th><th>Dernier état</th><th>Appels / échecs</th><th>Dernier succès</th><th>Dernier échec</th></tr></thead><tbody>
@forelse($apis as $api)<tr><th>{{ $api->service }}</th><td>{{ $api->last_status ?? 'Connexion échouée' }}</td><td>{{ $api->requests }} / {{ $api->failures }}</td><td>{{ $api->last_success_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? '—' }}</td><td>{{ $api->last_failure_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? '—' }}</td></tr>@empty<tr><td colspan="5">Aucun appel observé pour le moment.</td></tr>@endforelse
</tbody></table></div></section>
<section class="vod-social-panel"><h2>Tâches automatiques</h2><p class="vod-social-muted">Un passage de l’ordonnanceur doit être observé régulièrement. Les disponibilités sont vérifiées quotidiennement.</p>
<div class="vod-table-scroll"><table class="vod-admin-table"><thead><tr><th>Tâche</th><th>État</th><th>Début</th><th>Fin</th></tr></thead><tbody>
@forelse($tasks as $task)<tr><th>{{ $task->name }}</th><td>{{ ['success'=>'Réussie','failed'=>'En échec','running'=>'En cours'][$task->status] ?? $task->status }}@if((($task->name === 'schedule:heartbeat' && !$tasks->contains('name', 'cron:hourly') && $task->started_at->lt(now()->subMinutes(20))) || ($task->name === 'cron:hourly' && $task->started_at->lt(now()->subMinutes(90))))) · Passage ancien @endif @if($task->error_class)<small>{{ $task->error_class }}</small>@endif</td><td>{{ $task->started_at->timezone('Europe/Paris')->format('d/m/Y H:i') }}</td><td>{{ $task->finished_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? '—' }}</td></tr>@empty<tr><td colspan="4">Aucun passage enregistré. Vérifiez la tâche cron de l’ordonnanceur.</td></tr>@endforelse
</tbody></table></div>
<p>Dernière vérification de disponibilité : {{ $lastAvailabilityCheck ? \Carbon\Carbon::parse($lastAvailabilityCheck)->timezone('Europe/Paris')->format('d/m/Y H:i') : 'aucune' }}.</p></section>
<section class="vod-social-panel"><h2>Notifications</h2><div class="vod-table-scroll"><table class="vod-admin-table"><thead><tr><th>Type</th><th>Traitées</th><th>En reprise</th><th>Échecs définitifs</th></tr></thead><tbody>@foreach($deliveries as $label => $counts)<tr><th>{{ $label }}</th><td>{{ $counts['sent'] }}</td><td>{{ $counts['retry'] }}</td><td>{{ $counts['exhausted'] }}</td></tr>@endforeach</tbody></table></div><p class="vod-social-muted">Une livraison traitée indique l’acceptation par le fournisseur ou la suppression d’un abonnement push expiré ; elle ne confirme pas la lecture du message.</p><a href="{{ route('admin.logs') }}">Consulter les logs</a></section>
@endsection
