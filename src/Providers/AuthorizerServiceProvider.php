<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravarc\Authorizer\Authorization;
use Laravarc\Authorizer\Console\Commands\AuthorizerCommand;
use Laravarc\Authorizer\Contracts\AbilityRegistry;
use Laravarc\Authorizer\Contracts\TenantResolver;
use Laravarc\Authorizer\Gate\AuthorizerGateBefore;
use Laravarc\Authorizer\Http\Middleware\AuthorizeSuperMiddleware;
use Laravarc\Authorizer\Resolvers\ClassMapAbilityRegistry;
use Laravarc\Authorizer\RoleFactory;
use Laravarc\Authorizer\Services\AbilitySyncService;
use Laravarc\Authorizer\Services\AuthorizationService;
use Laravarc\Authorizer\Support\AbilityIndexBuilder;
use Laravarc\Authorizer\Support\NullTenantResolver;

final class AuthorizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/authorizer.php', 'authorizer');

        $this->app->singleton(AbilityIndexBuilder::class, function () {
            return new AbilityIndexBuilder(
                scanPaths: (array) config('authorizer.scan_paths', [base_path()]),
                scanNamespaces: (array) config('authorizer.scan_namespaces', []),
                excludeNamespaces: (array) config('authorizer.exclude_namespaces', []),
                excludeClasses: (array) config('authorizer.exclude_classes', []),
                includeClasses: (array) config('authorizer.include_classes', []),
            );
        });

        $this->app->singleton(ClassMapAbilityRegistry::class, function ($app) {
            return new ClassMapAbilityRegistry(
                builder: $app->make(AbilityIndexBuilder::class),
                files: $app->make('files'),
                cachePath: (string) config('authorizer.cache_path'),
            );
        });

        $this->app->bind(AbilityRegistry::class, ClassMapAbilityRegistry::class);
        $this->app->singleton(TenantResolver::class, NullTenantResolver::class);

        $this->app->singleton(AuthorizationService::class);
        $this->app->singleton(AbilitySyncService::class);
        $this->app->singleton(Authorization::class);
        $this->app->singleton(RoleFactory::class);
        $this->app->singleton(AuthorizerGateBefore::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->app->make('router')->aliasMiddleware(
            'laravarc.authorize.super',
            AuthorizeSuperMiddleware::class,
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuthorizerCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../config/authorizer.php' => config_path('authorizer.php'),
            ], 'authorizer-config');
        }

        Gate::before($this->app->make(AuthorizerGateBefore::class));
    }
}
