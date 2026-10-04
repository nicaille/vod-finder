<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Test only an isolated in-memory database. Never reset a developer database.
        if ($app['config']['database.default'] !== 'sqlite'
            || $app['config']['database.connections.sqlite.database'] !== ':memory:'
            || !empty($app['config']['database.connections.sqlite.url'])) {
            throw new \RuntimeException('Tests require an isolated SQLite :memory: database.');
        }

        $app->make(Kernel::class)->call('migrate', ['--force' => true]);
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;

        return $app;
    }
}
