@extends('pages.layout')
@section('title', 'Utilisateurs')
@section('content')
<section class="vod-intro"><span class="vod-eyebrow">Administration</span><h1>Utilisateurs</h1><p>Consulter les profils et leurs contenus.</p></section>@include('partials.admin-navigation')
<form method="GET" class="vod-filter-bar"><label>Nom, pseudo ou e-mail<input name="q" value="{{ $q }}" maxlength="100"></label><button>Rechercher</button></form>
<div class="vod-table-scroll"><table class="vod-admin-table"><thead><tr><th>Utilisateur</th><th>E-mail</th><th>Playlist</th><th>Déjà vus</th><th>Séries</th><th>Inscription</th></tr></thead><tbody>@forelse($users as $user)<tr><th><a href="{{ route('admin.user-data.show', $user) }}">{{ $user->nickname ?: $user->name }} @if($user->is_admin) · Admin @endif</a></th><td>{{ $user->email }}</td><td>{{ $user->watchlist_count }}</td><td>{{ $user->watched_titles_count }}</td><td>{{ $user->series_follows_count }}</td><td>{{ $user->created_at->timezone('Europe/Paris')->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="6">Aucun utilisateur.</td></tr>@endforelse</tbody></table></div>{{ $users->links() }}
@endsection
