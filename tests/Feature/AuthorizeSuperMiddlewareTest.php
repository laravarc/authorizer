<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravarc\Authorizer\Http\Middleware\AuthorizeSuperMiddleware;
use Laravarc\Authorizer\Services\AuthorizationService;
use Laravarc\Authorizer\Tests\Fixtures\User;

describe('laravarc.authorize.super middleware', function () {
    it('is registered as a middleware alias', function () {
        expect(app('router')->getMiddleware()['laravarc.authorize.super'] ?? null)
            ->toBe(AuthorizeSuperMiddleware::class);
    });

    it('allows requests from super users', function () {
        Route::middleware(['laravarc.authorize.super'])
            ->get('/arc-auth/super', fn () => response('ok'));

        $user = User::query()->create(['name' => 'Boss', 'email' => 'boss@t.test']);
        app(AuthorizationService::class)->createRole('Boss', isSystem: true, isSuper: true);
        app(AuthorizationService::class)->assignRole($user, 'Boss');

        $response = $this->actingAs($user)->get('/arc-auth/super');

        expect($response->status())->toBe(200)
            ->and($response->getContent())->toBe('ok');
    });

    it('returns forbidden for authenticated non-super users', function () {
        Route::middleware(['laravarc.authorize.super'])
            ->get('/arc-auth/super-deny', fn () => response('ok'));

        $user = User::query()->create(['name' => 'User', 'email' => 'user@t.test']);

        $response = $this->actingAs($user)->get('/arc-auth/super-deny');

        expect($response->status())->toBe(403);
    });

    it('returns forbidden for guests', function () {
        Route::middleware(['laravarc.authorize.super'])
            ->get('/arc-auth/super-guest', fn () => response('ok'));

        $response = $this->get('/arc-auth/super-guest');

        expect($response->status())->toBe(403);
    });
});
