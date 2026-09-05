<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Facades;

use Illuminate\Support\Facades\Facade;
use Laravarc\Authorizer\Fluent\PendingRoleCreate;

/**
 * @method static PendingRoleCreate create(string $name)
 *
 * @see \Laravarc\Authorizer\RoleFactory
 */
final class Role extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Laravarc\Authorizer\RoleFactory::class;
    }
}
