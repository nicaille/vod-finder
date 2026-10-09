<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function actingAs($user, $guard = null)
    {
        // Each identity switch simulates a new login, with its own password-bound session.
        $this->withSession(['password_hash_'.($guard ?? config('auth.defaults.guard')) => $user->getAuthPassword()]);
        return parent::actingAs($user, $guard);
    }

    protected function actingAsConfirmedAdmin(\App\Models\User $user): static
    {
        $this->withSession([
            'auth.password_confirmed_at' => time(),
            'auth.password_confirmed_user' => $user->id,
            'auth.password_confirmed_hash' => hash('sha256', $user->getAuthPassword()),
        ]);
        return $this->actingAs($user);
    }
}
