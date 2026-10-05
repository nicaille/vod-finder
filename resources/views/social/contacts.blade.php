@extends('social.layout')
@section('title','Mes contacts')
@section('content')
<h1>Mes contacts</h1><p>Rejoins tes proches pour échanger des idées de films, de séries et de personnes à découvrir.</p>
<div class="vod-social-columns">
<section class="vod-social-panel"><h2>Inviter par e-mail</h2><form action="{{ route('contacts.invite') }}" method="POST">@csrf<label>Adresse e-mail<input name="email" type="email" required maxlength="255" placeholder="Adresse de ton proche"></label><button>Envoyer une demande</button></form><p class="vod-social-muted">Pour un utilisateur déjà inscrit. Aucune adresse e-mail n’est affichée aux autres membres.</p></section>
<section class="vod-social-panel"><h2>Mon lien et mon QR code</h2>
@if(auth()->user()->contact_token)<img class="vod-contact-qr" src="{{ route('contacts.qr') }}" alt="QR code pour demander à rejoindre mon compte"><label>Lien à partager<input readonly value="{{ route('contacts.join',auth()->user()->contact_token) }}" onclick="this.select()"></label><p class="vod-social-muted">Le scan ouvre une demande à confirmer. Ton acceptation reste nécessaire.</p>@endif
<form method="POST" action="{{ route('contacts.rotate') }}">@csrf<button>{{ auth()->user()->contact_token ? 'Révoquer et renouveler le lien' : 'Créer mon lien et mon QR code' }}</button></form></section>
</div>
<section class="vod-social-panel"><h2>Mes relations et demandes</h2>
@forelse($connections as $connection)
@php $other=$connection->other(auth()->id()); @endphp
<article class="vod-contact-row"><div><strong>{{ $other->socialName() }}</strong><p class="vod-social-muted">{{ ['pending'=>'Demande en attente','accepted'=>'En relation','declined'=>'Demande refusée','blocked'=>'Relation bloquée'][$connection->status] }}</p></div><div class="vod-social-actions">
@php $actions=match($connection->status){'pending'=>$connection->requested_by===auth()->id()?['cancel'=>'Annuler']:['accept'=>'Accepter','decline'=>'Refuser'],'accepted'=>['disconnect'=>'Retirer le contact'],'blocked'=>$connection->blocked_by===auth()->id()?['unblock'=>'Débloquer']:[],default=>[]}; @endphp
@foreach($actions as $action=>$label)<form method="POST" action="{{ route('contacts.update',$connection) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="{{ $action }}"><button>{{ $label }}</button></form>@endforeach
@if($connection->status!=='blocked')<form method="POST" action="{{ route('contacts.update',$connection) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="block"><button>Bloquer</button></form>@endif
</div></article>
@empty<p>Tu n’as pas encore de contact.</p>@endforelse</section>
<section class="vod-social-panel"><h2>Trouver des utilisateurs</h2><p class="vod-social-muted">Seuls les membres ayant choisi d’apparaître dans l’annuaire sont listés.</p>
<form method="GET"><label>Pseudo ou nom partagé<input name="q" value="{{ $q }}" maxlength="80"></label><button>Rechercher</button></form>
@php $knownContacts=$connections->keyBy(fn($connection)=>$connection->other(auth()->id())->id); @endphp
@forelse($directory as $member)<article class="vod-contact-row"><strong>{{ $member->socialName() }}</strong>@if(isset($knownContacts[$member->id]))<span class="vod-social-muted">{{ $knownContacts[$member->id]->status==='accepted'?'Déjà en relation':'Voir la relation ci-dessus' }}</span>@else<form action="{{ route('contacts.invite') }}" method="POST">@csrf<input type="hidden" name="user_id" value="{{ $member->id }}"><button>Demander à rejoindre</button></form>@endif</article>@empty<p>Aucun utilisateur à afficher.</p>@endforelse{{ $directory->withQueryString()->links() }}</section>
@endsection
