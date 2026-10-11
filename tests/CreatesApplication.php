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

        // Defaults remain in-memory SQLite. MySQL is an explicit opt-in for a
        // disposable local database with a fixed name, never a developer DB.
        $connection = $app['config']['database.default'];
        $database = $app['config']["database.connections.$connection"];
        $isolatedSqlite = $connection === 'sqlite' && $database['database'] === ':memory:';
        $isolatedMysql = $connection === 'mysql'
            && getenv('VOD_TEST_MYSQL') === '1'
            && $database['database'] === 'vod_finder_test'
            && $database['username'] === 'vod_test'
            && $database['host'] === '127.0.0.1'
            && empty($database['unix_socket']);
        if ((!$isolatedSqlite && !$isolatedMysql) || !empty($database['url']) || !$app->environment('testing')) {
            throw new \RuntimeException('Tests require SQLite :memory: or explicitly isolated local MySQL.');
        }

        static $mysqlPrepared = false;
        $command = $isolatedMysql && !$mysqlPrepared ? 'migrate:fresh' : 'migrate';
        if ($app->make(Kernel::class)->call($command, ['--force' => true]) !== 0) {
            throw new \RuntimeException('Test database migrations failed.');
        }
        $mysqlPrepared = $mysqlPrepared || $isolatedMysql;
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = true;

        return $app;
    }
}
