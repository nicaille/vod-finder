@php $accountActivity = app(\App\Services\AccountActivity::class)->summary(auth()->user()); @endphp
<div class="vod-social-actions">
@if($accountActivity['recommendations'])<a href="{{ route('recommendations.index', ['state'=>'unread']) }}" data-activity-kind="recommendations">{{ $accountActivity['recommendations'] }} recommandation(s) non lue(s)</a>@endif
@if($accountActivity['contacts'])<a href="{{ route('contacts.index') }}" data-activity-kind="contacts">Contacts : {{ $accountActivity['pending'] }} demande(s) en attente, {{ $accountActivity['accepted'] }} acceptation(s) à consulter</a>@endif
@if($accountActivity['availability'])<a href="{{ route('availability.index', ['state'=>'unread']) }}" data-activity-kind="availability">{{ $accountActivity['availability'] }} alerte(s) de disponibilité non lue(s)</a>@endif
</div>
