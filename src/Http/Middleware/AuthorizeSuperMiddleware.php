<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user is an Authorizer super role (`is_super`).
 *
 * Alias: laravarc.authorize.super
 */
final class AuthorizeSuperMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! method_exists($user, 'isSuper') || ! $user->isSuper()) {
            abort(403, 'Super admin required.');
        }

        return $next($request);
    }
}
