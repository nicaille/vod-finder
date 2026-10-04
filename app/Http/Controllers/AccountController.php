<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function edit()
    {
        $user = Auth::user();

        $platforms = Platform::orderBy('position')->orderBy('name')->get();

        $subscriptions = $user->platformSubscriptions()->get()->keyBy('id');


        return view('account.edit', compact('user', 'platforms', 'subscriptions'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name'  => ['required', 'string', 'max:80'],
            'nickname'   => ['nullable', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'nickname')->ignore($user->id)],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'notify_opt_in' => ['nullable', 'boolean'],

            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['integer', 'exists:platforms,id'],

            'via' => ['nullable', 'array'],
            'via.*' => ['nullable', 'integer', 'exists:platforms,id'],

            'notify' => ['nullable', 'array'],
            'notify.*' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $user) {
            $user->first_name = $request->string('first_name')->toString();
            $user->last_name  = $request->string('last_name')->toString();
            $nickname = $request->string('nickname')->trim()->toString();
            $user->nickname = $nickname === '' ? null : $nickname;
            $user->email      = $request->string('email')->trim()->lower()->toString();

            // IMPORTANT: absent => false (si checkbox non envoyée)
            $user->notify_opt_in = $request->boolean('notify_opt_in');

            $user->name = trim($user->first_name . ' ' . $user->last_name);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();

            $platformIds = array_values(array_unique(array_map('intval', (array) $request->input('platforms', []))));
            $viaMap      = (array) $request->input('via', []);
            $notifyMap   = (array) $request->input('notify', []);

            $sync = [];
            foreach ($platformIds as $pid) {
                $viaId = !empty($viaMap[$pid]) ? (int) $viaMap[$pid] : null;

                // Normalisation: via = soi-même => null
                if ($viaId === $pid) {
                    $viaId = null;
                }

                $sync[$pid] = [
                    'subscribed_via_platform_id' => $viaId,
                    'is_active' => true,

                    // Choix produit:
                    // - si tu veux "absent => true", remets true ici
                    // - sinon false (recommandé)
                    'notify_opt_in' => array_key_exists($pid, $notifyMap) ? (bool) $notifyMap[$pid] : false,
                ];
            }

            $user->platformSubscriptions()->sync($sync);
        });

        return redirect()->route('account.edit')->with('status', 'Compte mis à jour.');
    }
}
