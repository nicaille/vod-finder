<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->safe()->except('current_password'));

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            $admins = \App\Models\User::where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            if ($admins->contains('id', $user->id) && $admins->count() === 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['password' => 'Ajoute un autre administrateur avant de supprimer le dernier compte administrateur.'])->errorBag('userDeletion');
            }
            // Logout rotates the remember token; do it before deletion to avoid re-saving a deleted model.
            Auth::logout();
            $user->delete();
        }, 3);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
