<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index()
    {
        $administrators = User::where('is_admin', true)->orderBy('email')->get();
        return view('admin.users', compact('administrators'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc,filter', 'max:255']]);
        DB::transaction(function () use ($request, $data) {
            $admins = User::where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            abort_unless($admins->contains('id', $request->user()->id), 403);
            $user = User::where('email', $data['email'])->lockForUpdate()->first();
            if (!$user) throw ValidationException::withMessages(['email' => 'Aucun compte ne correspond à cette adresse. La personne doit d’abord créer son compte.']);
            if (!$user->hasVerifiedEmail()) throw ValidationException::withMessages(['email' => 'Ce compte doit confirmer son adresse e-mail avant de recevoir les droits d’administration.']);
            $user->forceFill(['is_admin' => true])->save();
            \Illuminate\Support\Facades\Log::channel('security')->info('admin.granted', ['actor_id' => $request->user()->id, 'target_user_id' => $user->id]);
        }, 3);
        return back()->with('status', 'Les droits d’administration ont été attribués.');
    }

    public function destroy(Request $request, User $user)
    {
        DB::transaction(function () use ($request, $user) {
            $admins = User::where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            abort_unless($admins->contains('id', $request->user()->id), 403);
            if ($admins->contains('id', $user->id) && $admins->count() === 1) {
                throw ValidationException::withMessages(['administrator' => 'Ajoutez un autre administrateur avant de retirer les droits du dernier administrateur.']);
            }
            User::whereKey($user->id)->update(['is_admin' => false]);
            \Illuminate\Support\Facades\Log::channel('security')->info('admin.revoked', ['actor_id' => $request->user()->id, 'target_user_id' => $user->id]);
        }, 3);
        return redirect()->route($user->id === $request->user()->id ? 'about.show' : 'admin.users.index')
            ->with('status', 'Les droits d’administration ont été retirés.');
    }
}
