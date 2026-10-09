@extends('pages.layout')
@section('title', 'Données utilisateur')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration · utilisateur #{{ $user->id }}</span><h1>{{ $user->nickname ?: $user->name }}</h1><p>{{ $user->email }}</p></section>@include('partials.admin-navigation')
<nav class="vod-social-tabs" aria-label="Données utilisateur">@foreach(['profile'=>'Profil','playlist'=>'Playlist','watched'=>'Déjà vus','favorites'=>'Coups de cœur','lists'=>'Listes','series'=>'Séries suivies','subscriptions'=>'Abonnements','recommendations'=>'Recommandations','contacts'=>'Contacts'] as $value=>$label)<a href="{{ route('admin.user-data.show', ['user'=>$user,'tab'=>$value]) }}" @if($tab === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
@if($tab === 'profile')<section class="vod-social-panel"><dl class="vod-admin-profile">
@foreach(['Prénom'=>$user->first_name,'Nom'=>$user->last_name,'Pseudo'=>$user->nickname,'E-mail'=>$user->email,'Vérifié'=>$user->email_verified_at?->timezone('Europe/Paris')->format('d/m/Y H:i') ?? 'Non','Administrateur'=>$user->is_admin?'Oui':'Non','Inscrit le'=>$user->created_at->timezone('Europe/Paris')->format('d/m/Y H:i'),'Dernière modification'=>$user->updated_at->timezone('Europe/Paris')->format('d/m/Y H:i'),'Notifications générales'=>$user->notify_opt_in?'Oui':'Non','Alertes disponibilité'=>$user->notify_platform_updates?'Oui':'Non','E-mails'=>$user->notify_email?'Oui':'Non','Push'=>$user->notify_web?'Oui':'Non','Visible dans l’annuaire'=>$user->directory_visible?'Oui':'Non','Partage du vrai nom'=>$user->share_real_name?'Oui':'Non'] as $label=>$value)<dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd>@endforeach
</dl></section>
@else
@if($lists)<section class="vod-social-panel"><h2>Listes possédées</h2>@forelse($lists as $list)<p>{{ $list->name }} · {{ $list->items_count }} titre(s) · {{ $list->is_public ? 'Publique' : 'Privée' }}</p>@empty<p>Aucune liste.</p>@endforelse{{ $lists->links() }}</section>@endif
<section class="vod-social-panel"><h2>{{ ['playlist'=>'Playlist','watched'=>'Déjà vus','favorites'=>'Coups de cœur','lists'=>'Contenus des listes possédées ou partagées','series'=>'Séries suivies','subscriptions'=>'Abonnements','recommendations'=>'Recommandations','contacts'=>'Contacts'][$tab] }}</h2>
@forelse($rows as $row)<div class="vod-admin-row">
@if(in_array($tab, ['playlist','watched','favorites','lists']))<a href="{{ route('content.show', ['type'=>$row->type,'id'=>$row->tmdb_id]) }}">{{ $row->title ?? $knownTitles->get($row->type.':'.$row->tmdb_id) ?? (($row->type === 'tv' ? 'Série' : 'Film').' #'.$row->tmdb_id) }}</a><span>{{ $row->type === 'tv' ? 'Série' : 'Film' }} · TMDb {{ $row->tmdb_id }} @if($tab === 'lists') · {{ $row->list->name }} @endif @if($tab === 'watched') · {{ $row->watched_at->timezone('Europe/Paris')->format('d/m/Y') }} @endif</span>
@elseif($tab === 'series')<a href="{{ route('content.show', ['type'=>'tv','id'=>$row->series->tmdb_id]) }}">{{ $row->series->name }}</a><span>Alertes {{ $row->alerts_enabled ? 'actives' : 'désactivées' }}</span>
@elseif($tab === 'subscriptions')<strong>{{ $row->name }}</strong><span>{{ $row->pivot->is_active ? 'Actif' : 'Inactif' }} · {{ $viaNames->get($row->pivot->subscribed_via_platform_id) ? 'via '.$viaNames->get($row->pivot->subscribed_via_platform_id) : 'Direct' }} · Alertes {{ $row->pivot->notify_opt_in ? 'actives' : 'désactivées' }}</span>
@elseif($tab === 'recommendations')<a href="{{ route('content.show', ['type'=>$row->type,'id'=>$row->tmdb_id]) }}">{{ $row->content['title'] ?? 'Titre #'.$row->tmdb_id }}</a><span>{{ $row->sender?->name ?? 'Compte supprimé' }} → {{ $row->recipient?->name ?? 'Compte supprimé' }} · {{ $row->created_at->timezone('Europe/Paris')->format('d/m/Y H:i') }}</span>@if($row->message)<p>{{ $row->message }}</p>@endif
@elseif($tab === 'contacts')<a href="{{ route('admin.user-data.show', $row->other($user->id)) }}">{{ $row->other($user->id)->nickname ?: $row->other($user->id)->name }}</a><span>{{ ['accepted'=>'Accepté','pending'=>'En attente','declined'=>'Refusé','blocked'=>'Bloqué'][$row->status] ?? $row->status }}</span>
@endif
</div>@empty<p>Aucune donnée dans cette rubrique.</p>@endforelse
{{ $rows->links() }}</section>
@endif
@endsection
