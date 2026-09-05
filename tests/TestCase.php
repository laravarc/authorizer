<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravarc\Authorizer\Providers\AuthorizerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            AuthorizerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        if (extension_loaded('pdo_sqlite')) {
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        } else {
            $app['config']->set('database.connections.testing', [
                'driver' => 'mysql',
                'host' => env('AUTHORIZER_DB_HOST', '127.0.0.1'),
                'port' => env('AUTHORIZER_DB_PORT', '3307'),
                'database' => env('AUTHORIZER_DB_DATABASE', 'authorizer_test'),
                'username' => env('AUTHORIZER_DB_USERNAME', 'root'),
                'password' => env('AUTHORIZER_DB_PASSWORD', 'root'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
        }

        $app['config']->set('authorizer.scan_paths', [
            dirname(__DIR__),
        ]);
        $app['config']->set('authorizer.scan_namespaces', [
            'Laravarc\\Authorizer\\Tests\\Fixtures\\Policies\\',
        ]);
        $app['config']->set('authorizer.exclude_namespaces', [
            'Laravarc\\Authorizer\\Tests\\Fixtures\\Duplicate\\',
        ]);
        $app['config']->set(
            'authorizer.cache_path',
            sys_get_temp_dir().'/authorizer-abilities-'.uniqid('', true).'.php',
        );
        $app['config']->set('authorizer.user_model', \Laravarc\Authorizer\Tests\Fixtures\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }
}
