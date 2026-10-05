@extends('social.layout')
@section('title','Mes recommandations')
@section('content')<h1>Mes recommandations</h1><p>Les découvertes que tes contacts t’ont envoyées.</p>
<form class="vod-social-filters" method="GET">
<label>Titre<input name="q" value="{{ request('q') }}" maxlength="100"></label>
<label>Type<select name="type"><option value="">Tous</option>@foreach(['movie'=>'Films','tv'=>'Séries','person'=>'Personnes'] as $value=>$label)<option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Expéditeur<select name="sender"><option value="">Tous</option>@foreach($senders as $sender)<option value="{{ $sender->id }}" @selected((string)request('sender')===(string)$sender->id)>{{ $sender->socialName() }}</option>@endforeach</select></label>
<label>État<select name="state">@foreach(['all'=>'Reçues','unread'=>'Non lues','archived'=>'Archivées'] as $value=>$label)<option value="{{ $value }}" @selected(request('state','all')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Plateforme<select name="platform"><option value="">Toutes</option>@foreach(['netflix'=>'Netflix','prime'=>'Prime Video','disneyplus'=>'Disney+','canalplus'=>'Canal+','appletv'=>'Apple TV','paramountplus'=>'Paramount+','hbomax'=>'HBO Max'] as $value=>$label)<option value="{{ $value }}" @selected(request('platform')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Trier<select name="sort">@foreach(['newest'=>'Plus récentes','oldest'=>'Plus anciennes','title'=>'Titre A–Z'] as $value=>$label)<option value="{{ $value }}" @selected(request('sort','newest')===$value)>{{ $label }}</option>@endforeach</select></label><button>Appliquer</button><a href="{{ route('recommendations.index') }}">Réinitialiser</a></form>
<div class="vod-recommendation-grid">@forelse($recommendations as $recommendation)@include('social.card')@empty<p>Aucune recommandation pour ces filtres.</p>@endforelse</div>{{ $recommendations->withQueryString()->links() }}
@endsection
