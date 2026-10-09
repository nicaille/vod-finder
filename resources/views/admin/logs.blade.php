@extends('pages.layout')
@section('title', 'Logs')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration</span><h1>Logs de l’application</h1><p>Consultation de la fin des journaux, limitée aux 100 dernières entrées et 500 lignes dans les derniers 256 Ko. Les secrets usuels sont masqués.</p></section>
@include('partials.admin-navigation')
<form method="GET" class="vod-social-panel vod-filter-bar"><label>Fichier<select name="file">@foreach($files as $name)<option value="{{ $name }}" @selected($file === $name)>{{ $name }}</option>@endforeach</select></label><label>Niveau<select name="level">@foreach(['all'=>'Tous','debug'=>'Debug','info'=>'Info','notice'=>'Notice','warning'=>'Warning','error'=>'Error','critical'=>'Critical','alert'=>'Alert','emergency'=>'Emergency'] as $value=>$label)<option value="{{ $value }}" @selected($level === $value)>{{ $label }}</option>@endforeach</select></label><button type="submit">Afficher / actualiser</button></form>
<pre class="vod-log-view" tabindex="0">{{ $text }}</pre>
@endsection
