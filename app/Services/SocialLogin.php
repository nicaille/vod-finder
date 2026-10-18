<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use Composer\CaBundle\CaBundle;

class SocialLogin
{
    public const PROVIDERS = ['google' => 'Google', 'microsoft' => 'Microsoft', 'facebook' => 'Facebook'];

    public static function available(): array
    {
        return array_filter(self::PROVIDERS, fn ($name) => self::enabled($name), ARRAY_FILTER_USE_KEY);
    }

    public static function enabled(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider]) && config('services.'.$provider.'.enabled')
            && filled(config('services.'.$provider.'.client_id')) && filled(config('services.'.$provider.'.client_secret'))
            && filled(config('services.'.$provider.'.redirect'));
    }

    public function driver(string $provider)
    {
        abort_unless(self::enabled($provider), 404);
        Socialite::forgetDrivers(); // Bind each driver to the current HTTP request/session.
        $driver = Socialite::driver($provider)->setHttpClient(new \GuzzleHttp\Client([
            'timeout' => 15, 'connect_timeout' => 5,
            'verify' => ini_get('curl.cainfo') ?: CaBundle::getSystemCaRootBundlePath(),
            'allow_redirects' => false,
        ]));
        if ($provider === 'google' || $provider === 'microsoft') $driver->enablePKCE();
        if ($provider === 'google') $driver->with(['prompt' => 'select_account']);
        if ($provider === 'microsoft') $driver->with(['prompt' => 'select_account']);
        if ($provider === 'facebook') $driver->fields(['name', 'email', 'first_name', 'last_name']);
        return $driver;
    }

    public function identity(string $provider): array
    {
        $remote = $this->driver($provider)->user(); // Stateful Socialite validation; never use stateless().
        $id = (string) $remote->getId();
        if ($id === '' || strlen($id) > 191) throw new \RuntimeException('Invalid provider identity');
        $raw = $remote->getRaw();
        $email = $provider === 'microsoft' ? ($raw['mail'] ?? $remote->getEmail()) : $remote->getEmail();
        $email = is_string($email) ? mb_strtolower(trim($email)) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) $email = '';
        $verified = $provider === 'google' && in_array($raw['email_verified'] ?? $raw['verified_email'] ?? false, [true, 1, '1', 'true'], true);
        $name = preg_split('/\s+/', trim((string) $remote->getName()), 2);
        // Keep only the identity proof and form defaults. Never persist OAuth access/refresh tokens.
        return ['provider' => $provider, 'provider_id' => $id, 'email' => $email, 'email_verified' => $verified,
            'first_name' => mb_substr((string) ($raw['given_name'] ?? $raw['givenName'] ?? $raw['first_name'] ?? $name[0] ?? ''), 0, 80),
            'last_name' => mb_substr((string) ($raw['family_name'] ?? $raw['surname'] ?? $raw['last_name'] ?? $name[1] ?? ''), 0, 80),
            'expires_at' => now()->addMinutes(10)->timestamp];
    }
}
