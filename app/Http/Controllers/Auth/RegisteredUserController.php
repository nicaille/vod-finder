<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create()
    {
        // Si tu as une colonne position, préfère orderBy('position')
        $platforms = Platform::orderBy('position')->orderBy('name')->get();
        return view('auth.register', compact('platforms'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name'  => ['required', 'string', 'max:80'],

            'nickname' => ['nullable', 'string', 'max:80', 'alpha_dash', new \App\Rules\AvailableNickname()],

            'email'    => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // opt-in global (nom actuel chez toi)
            'notify_opt_in' => ['nullable', 'boolean'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_web' => ['nullable', 'boolean'],

            // Liste d'IDs de plateformes cochées
            'platforms'   => ['nullable', 'array'],
            'platforms.*' => ['integer', 'exists:platforms,id'],

            // via est une map: via[platform_id] = via_platform_id
            'via'   => ['nullable', 'array'],
            'via.*' => ['nullable', 'integer', 'exists:platforms,id'],

            // notifications par plateforme: notify[platform_id] = 1|0
            'notify'   => ['nullable', 'array'],
            'notify.*' => ['nullable', 'boolean'],
        ]);

        // Normalisations
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName  = trim((string) $request->input('last_name', ''));

        $nicknameRaw = $request->input('nickname');
        $nickname = is_string($nicknameRaw) ? trim($nicknameRaw) : null;
        if ($nickname === '') {
            $nickname = null;
        }

        $email = strtolower(trim((string) $request->input('email', '')));

        $platformIds = array_values(array_unique(array_map(
            static fn ($v) => (int) $v,
            (array) $request->input('platforms', [])
        )));
        $platformIds = array_values(array_filter($platformIds, static fn ($v) => $v > 0));

        $viaMap = (array) $request->input('via', []);
        $notifyMap = (array) $request->input('notify', []);

        $user = DB::transaction(function () use (
            $request,
            $firstName,
            $lastName,
            $nickname,
            $email,
            $platformIds,
            $viaMap,
            $notifyMap
        ) {
            $user = User::create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'nickname'   => $nickname,
                'email'      => $email,

                // opt-in global (chez toi: notify_opt_in)
                'notify_opt_in' => $request->boolean('notify_opt_in'),
                'notify_email' => $request->boolean('notify_email'),
                'notify_web' => $request->boolean('notify_web'),

                // si ton app utilise "name" ailleurs, on le garde propre
                'name' => trim($firstName . ' ' . $lastName),

                'password' => Hash::make((string) $request->input('password')),
            ]);

            // Sync pivot enrichi
            // IMPORTANT: on ne garde "via" que si la plateforme est effectivement cochée
            // + on évite via = soi-même
            $sync = [];

            foreach ($platformIds as $pid) {
                $viaId = null;

                if (array_key_exists($pid, $viaMap) && $viaMap[$pid] !== null && $viaMap[$pid] !== '') {
                    $viaId = (int) $viaMap[$pid];
                    if ($viaId <= 0) {
                        $viaId = null;
                    }
                }

                if ($viaId !== null) {
                    // évite via = soi-même
                    if ($viaId === $pid) {
                        $viaId = null;
                    }
                    // évite via qui n'existe pas réellement (double sécurité)
                    if ($viaId !== null && !Platform::whereKey($viaId)->exists()) {
                        $viaId = null;
                    }
                }

                // notify par plateforme: si non fourni, on met true par défaut (comme ton code)
                $perPlatformNotify = array_key_exists($pid, $notifyMap)
                    ? (bool) $notifyMap[$pid]
                    : true;

                $sync[$pid] = [
                    // ⚠️ Ces colonnes doivent exister dans user_platform_subscriptions
                    'subscribed_via_platform_id' => $viaId,
                    'is_active' => true,
                    'notify_opt_in' => $perPlatformNotify,
                ];
            }

            if (!empty($sync)) {
                // ⚠️ Cette relation doit exister dans User (voir rappel plus bas)
                $user->platformSubscriptions()->sync($sync);
            }

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        if ($user->notify_opt_in && ($user->notify_email || $user->notify_web)) {
            $message = 'Compte créé. Pour les notifications navigateur, autorise chaque appareil ci-dessous puis enregistre tes préférences.';
            if ($user->notify_email) {
                try {
                    app(\App\Services\NotificationEmailVerification::class)->send($user);
                    $message = 'Compte créé. Un lien de confirmation de ton adresse a été envoyé : ouvre-le pour activer les alertes par e-mail. Si tu as choisi le navigateur, autorise aussi cet appareil ci-dessous.';
                } catch (\Throwable) {
                    $message = 'Compte créé. Le lien de confirmation n’a pas pu être envoyé. Réessaie avec le bouton de confirmation ci-dessous. Les alertes par e-mail restent en attente.';
                }
            }
            return redirect(route('account.edit').'#notifications')->with('status', $message);
        }

        // Redirection vers la recherche
        return redirect()->intended(route('search.index'));
    }
}
