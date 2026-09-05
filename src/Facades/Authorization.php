<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Facades;

use Illuminate\Support\Facades\Facade;
use Laravarc\Authorizer\Fluent\PendingRoleGrant;
use Laravarc\Authorizer\Fluent\PendingUserAuthorization;

/**
 * @method static PendingRoleGrant role(string $name)
 * @method static PendingUserAuthorization user(object $user)
 * @method static \Laravarc\Authorizer\Fluent\PendingRoleCreate createRole(string $name)
 *
 * @see \Laravarc\Authorizer\Authorization
 */
final class Authorization extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laravarc\Authorizer\Authorization::class;
    }
}
