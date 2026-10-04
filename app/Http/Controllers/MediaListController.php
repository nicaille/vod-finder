<?php

namespace App\Http\Controllers;

use App\Models\MediaList;
use App\Models\ListItem;
use App\Models\Favorite;
use App\Services\TmdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MediaListController extends Controller
{
    /**
     * Page "❤️ Mes listes"
     */
    public function index(Request $request, TmdbService $tmdb)
    {
        $user = $request->user();

        // 1) Récupération des listes de l'utilisateur + items
        $ownLists = MediaList::with('items')
            ->where('user_id', $user->id)
            ->get();

        $listsForView = [];

        foreach ($ownLists as $list) {
            $itemsView = [];

            foreach ($list->items as $item) {
                // On essaie d'enrichir avec TMDb pour avoir un titre/poster à jour
                $details = $tmdb->getDetails($item->tmdb_id, $item->type);

                if (!$details) {
                    $itemsView[] = [
                        'id'      => $item->id,
                        'tmdb_id' => $item->tmdb_id,
                        'type'    => $item->type,
                        'title'   => $item->title ?? 'Titre introuvable',
                        'year'    => $item->year ?? null,
                        'poster'  => $item->poster ?? null,
                    ];
                    continue;
                }

                $title = $details['title'] ?? $details['name'] ?? ($item->title ?? 'Sans titre');
                $date  = $details['release_date'] ?? $details['first_air_date'] ?? null;
                $year  = $date ? substr($date, 0, 4) : ($item->year ?? null);
                $poster = !empty($details['poster_path'])
                    ? 'https://image.tmdb.org/t/p/w185' . $details['poster_path']
                    : ($item->poster ?? null);

                $itemsView[] = [
                    'id'      => $item->id,
                    'tmdb_id' => $item->tmdb_id,
                    'type'    => $item->type,
                    'title'   => $title,
                    'year'    => $year,
                    'poster'  => $poster,
                ];
            }

            $listsForView[] = [
                'id'          => $list->id,
                'name'        => $list->name,
                'description' => $list->description,
                'is_public'   => (bool) $list->is_public,
                'items'       => $itemsView,
            ];
        }

        // 2) Coups de cœur
        $favoritesRaw = Favorite::where('user_id', $user->id)->get();
        $favoritesForView = [];

        foreach ($favoritesRaw as $fav) {
            $details = $tmdb->getDetails($fav->tmdb_id, $fav->type);

            if (!$details) {
                $favoritesForView[] = [
                    'id'      => $fav->id,
                    'tmdb_id' => $fav->tmdb_id,
                    'type'    => $fav->type,
                    'title'   => $fav->title ?? 'Titre introuvable',
                    'year'    => $fav->year ?? null,
                    'poster'  => $fav->poster ?? null,
                ];
                continue;
            }

            $title = $details['title'] ?? $details['name'] ?? ($fav->title ?? 'Sans titre');
            $date  = $details['release_date'] ?? $details['first_air_date'] ?? null;
            $year  = $date ? substr($date, 0, 4) : ($fav->year ?? null);
            $poster = !empty($details['poster_path'])
                ? 'https://image.tmdb.org/t/p/w185' . $details['poster_path']
                : ($fav->poster ?? null);

            $favoritesForView[] = [
                'id'      => $fav->id,
                'tmdb_id' => $fav->tmdb_id,
                'type'    => $fav->type,
                'title'   => $title,
                'year'    => $year,
                'poster'  => $poster,
            ];
        }

        return view('lists.index', [
            'lists'     => $listsForView,
            'favorites' => $favoritesForView,
        ]);
    }

    /**
     * Création d'une liste (depuis la popin ou autre)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'is_public'        => 'sometimes|boolean',
            'is_collaborative' => 'sometimes|boolean',
        ]);

        $data['user_id'] = $request->user()->id;
        $data['is_public'] = (bool) ($data['is_public'] ?? false);
        $data['is_collaborative'] = (bool) ($data['is_collaborative'] ?? false);

        $list = MediaList::create($data);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'list'    => $list,
            ]);
        }

        return redirect()->route('lists.index');
    }

    /**
     * Mise à jour d'une liste (nom, description, visibilité, etc.)
     */
    public function update(Request $request, MediaList $list)
    {
        // Vérifie que la liste appartient bien à l'utilisateur connecté
        $this->authorizeList($list);

        // On valide ce qui arrive (JSON ou form classique)
        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:255',
            'description'      => 'nullable|string',
            'is_public'        => 'sometimes|boolean',
            'is_collaborative' => 'sometimes|boolean',
        ]);

        // On ne met à jour que ce qui est vraiment présent dans la requête
        $dataToUpdate = [];

        if ($request->has('name')) {
            $dataToUpdate['name'] = $validated['name'];
        }

        if ($request->has('description')) {
            // description peut être null ou string
            $dataToUpdate['description'] = $validated['description'] ?? null;
        }

        if ($request->has('is_public')) {
            $dataToUpdate['is_public'] = (bool) $validated['is_public'];
        }

        if ($request->has('is_collaborative')) {
            $dataToUpdate['is_collaborative'] = (bool) $validated['is_collaborative'];
        }

        // Si rien à mettre à jour, on renvoie juste OK
        if (empty($dataToUpdate)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'list'    => $list,
                    'message' => 'Aucune modification.',
                ]);
            }

            return redirect()
                ->route('lists.index')
                ->with('status', 'Aucune modification.');
        }

        // Mise à jour effective
        $list->update($dataToUpdate);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'list'    => $list->fresh(),
            ]);
        }

        return redirect()
            ->route('lists.index')
            ->with('status', 'Liste mise à jour.');
    }


    /**
     * Suppression d'une liste entière
     */
    public function destroy(Request $request, MediaList $list)
    {
        $this->authorizeList($list);

        $list->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('lists.index');
    }

    /**
     * Ajout d'un item dans une liste
     */
    public function addItem(Request $request, MediaList $list)
    {
        $this->authorizeList($list);

        $data = $request->validate([
            'tmdb_id' => 'required|integer',
            'type'    => 'required|string|in:movie,tv',
        ]);

        // Évite les doublons (tmdb_id + type dans la même liste)
        $item = ListItem::firstOrCreate(
            [
                'list_id' => $list->id,
                'tmdb_id' => $data['tmdb_id'],
                'type'    => $data['type'],
            ],
            [
                'added_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'success' => true,
            'item'    => $item,
        ]);
    }

    /**
     * Suppression d'un item dans une liste (route DELETE /lists/{list}/items/{item})
     */
    public function destroyItem(Request $request, MediaList $list, ListItem $item)
    {
        $user = $request->user();

        // La liste doit appartenir à l'utilisateur
        if ($list->user_id !== $user->id) {
            abort(403);
        }

        // L'item doit bien appartenir à cette liste
        // (on part sur la colonne "list_id" qui est cohérente avec addItem)
        if ($item->list_id !== $list->id) {
            abort(404);
        }

        $item->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('lists.index');
    }

    /**
     * Autorisation simple : seul le propriétaire de la liste peut modifier.
     * (on étendra plus tard avec les membres / rôles).
     */
    protected function authorizeList(MediaList $list): void
    {
        if ($list->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
