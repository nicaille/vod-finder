<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{SocialAccount, User};
use App\Services\{SocialLogin, NotificationEmailVerification};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SocialLoginController extends Controller
{
    public function redirect(Request $request, string $provider, SocialLogin $login)
    {
        abort_unless(SocialLogin::enabled($provider), 404);
        $request->session()->forget(['oauth.pending', 'state', 'code_verifier']);
        $request->session()->put('oauth.flow', ['provider' => $provider,
            'user_id' => $request->user()?->id, 'expires_at' => now()->addMinutes(10)->timestamp]);
        try { return $login->driver($provider)->redirect(); }
        catch (\Throwable $exception) { return $this->failure($request, $exception, $provider); }
    }

    public function callback(Request $request, string $provider, SocialLogin $login)
    {
        abort_unless(SocialLogin::enabled($provider), 404);
        $flow = $request->session()->pull('oauth.flow');
        $state = $request->session()->get('state');
        if (!is_array($flow) || ($flow['provider'] ?? null) !== $provider || ($flow['expires_at'] ?? 0) < now()->timestamp
            || ($flow['user_id'] ?? null) !== $request->user()?->id || !is_string($state) || $state === ''
            || !is_string($request->query('state')) || !hash_equals($state, $request->query('state'))) {
            return $this->failure($request, null, $provider);
        }
        if ($request->has('error')) return $this->failure($request, null, $provider, 'Connexion annulée. Tu peux réessayer ou utiliser ton adresse e-mail.');
        if (!is_string($request->query('code')) || strlen($request->query('code')) > 4096 || $request->query('code') === '') return $this->failure($request, null, $provider);
        try {
            $identity = $login->identity($provider);
            $request->session()->forget(['state', 'code_verifier']);
            $account = SocialAccount::where('provider', $provider)->where('provider_id', $identity['provider_id'])->first();
            if ($request->user()) {
                DB::transaction(function () use ($request, $provider, $identity) {
                    $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                    $existing = SocialAccount::where('provider', $provider)->where('provider_id', $identity['provider_id'])->first();
                    if ($existing && $existing->user_id !== $user->id) throw new \RuntimeException('Identity already linked');
                    $sameProvider = $user->socialAccounts()->where('provider', $provider)->first();
                    if ($sameProvider && $sameProvider->provider_id !== $identity['provider_id']) throw new \RuntimeException('Provider already linked');
                    if (!$existing) $user->socialAccounts()->create(['provider' => $provider, 'provider_id' => $identity['provider_id']]);
                });
                return redirect()->route('account.edit')->with('status', 'Méthode de connexion associée à ton compte.');
            }
            if ($account) {
                $this->signIn($request, $account->user);
                return redirect()->route('search.index');
            }
            // An email match is never sufficient to link an existing account.
            if ($identity['email'] !== '' && User::whereRaw('LOWER(email) = ?', [$identity['email']])->exists()) {
                return $this->failure($request, null, $provider, 'Connecte-toi à ton compte existant, puis associe ce fournisseur depuis Mon compte.');
            }
            $request->session()->put('oauth.pending', $identity);
            return redirect()->route('oauth.signup');
        } catch (\Throwable $exception) {
            return $this->failure($request, $exception, $provider);
        }
    }

    public function signup(Request $request)
    {
        $identity = $this->pending($request);
        if (!$identity) return $this->failure($request);
        return view('auth.social-signup', compact('identity'));
    }

    public function register(Request $request)
    {
        $identity = $this->pending($request);
        if (!$identity) return $this->failure($request);
        $request->merge(['email' => is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : $request->input('email')]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'nickname' => ['nullable', 'string', 'max:80', 'alpha_dash', new \App\Rules\AvailableNickname()],
            'email' => ['required', 'email:rfc,filter', 'max:255', Rule::unique('users', 'email'),
                function ($attribute, $value, $fail) { if (User::whereRaw('LOWER(email) = ?', [$value])->exists()) $fail('Cette adresse est déjà utilisée. Connecte-toi au compte existant pour associer ce fournisseur.'); },
                Rule::when($identity['email_verified'], [Rule::in([$identity['email']])])],
            'notify_opt_in' => ['nullable', 'boolean'], 'notify_email' => ['nullable', 'boolean'], 'notify_web' => ['nullable', 'boolean'],
        ]);
        try {
            $user = DB::transaction(function () use ($data, $identity, $request) {
                $user = new User(['first_name' => trim($data['first_name']), 'last_name' => trim($data['last_name']),
                    'name' => trim($data['first_name'].' '.$data['last_name']), 'nickname' => $data['nickname'] ?? null,
                    'email' => $data['email'], 'password' => Str::random(64),
                    'notify_opt_in' => $request->boolean('notify_opt_in'), 'notify_email' => $request->boolean('notify_email'),
                    'notify_web' => $request->boolean('notify_web'), 'directory_visible' => false, 'share_real_name' => false]);
                $user->forceFill(['has_local_password' => false,
                    'email_verified_at' => $identity['email_verified'] && $identity['email'] === $data['email'] ? now() : null])->save();
                $user->socialAccounts()->create(['provider' => $identity['provider'], 'provider_id' => $identity['provider_id']]);
                return $user;
            });
        } catch (\Throwable $exception) { return $this->failure($request, $exception, $identity['provider']); }
        $request->session()->forget('oauth.pending');
        $this->signIn($request, $user);
        $message = 'Compte créé. Choisis maintenant tes abonnements dans Mon compte.';
        if ($user->notify_opt_in && $user->notify_email && !$user->hasVerifiedEmail()) {
            try { app(NotificationEmailVerification::class)->send($user); $message .= ' Confirme ton adresse avec le lien reçu par e-mail.'; }
            catch (\Throwable) { $message .= ' Le lien de confirmation n’a pas pu être envoyé ; tu peux le redemander depuis Mon compte.'; }
        }
        return redirect()->route('account.edit')->with('status', $message)->with('offer_push_permission', $user->notify_opt_in);
    }

    public function unlink(Request $request, string $provider)
    {
        abort_unless(isset(SocialLogin::PROVIDERS[$provider]), 404);
        $removed = DB::transaction(function () use ($request, $provider) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $accounts = $user->socialAccounts()->get();
            if (!$user->has_local_password && $accounts->count() <= 1) return false;
            $user->socialAccounts()->where('provider', $provider)->delete();
            return true;
        });
        return redirect()->route('account.edit')->with('status', $removed ? 'Méthode de connexion retirée.' : 'Conserve au moins une méthode de connexion ou définis d’abord un mot de passe.');
    }

    private function pending(Request $request): ?array
    {
        $identity = $request->session()->get('oauth.pending');
        return is_array($identity) && ($identity['expires_at'] ?? 0) >= now()->timestamp
            && SocialLogin::enabled($identity['provider'] ?? '') ? $identity : null;
    }

    private function signIn(Request $request, User $user): void
    {
        Auth::login($user); // No automatic remember-me, admin confirmation or preference changes.
        $request->session()->regenerate();
        $request->session()->forget(['auth.password_confirmed_at', 'auth.password_confirmed_user', 'auth.password_confirmed_hash']);
        $request->session()->put('password_hash_web', $user->getAuthPassword());
    }

    private function failure(Request $request, ?\Throwable $exception = null, ?string $provider = null, ?string $message = null)
    {
        $request->session()->forget(['oauth.flow', 'oauth.pending', 'state', 'code_verifier']);
        if ($exception) Log::warning('Social login failed', ['provider' => $provider, 'exception_class' => get_class($exception)]);
        return redirect()->route($request->user() ? 'account.edit' : 'login')->withErrors(['oauth' => $message ?? 'Connexion non aboutie ou expirée. Réessaie ; aucune association de compte n’a été modifiée.']);
    }
}
