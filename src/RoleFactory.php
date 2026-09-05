<?php

declare(strict_types=1);

namespace Laravarc\Authorizer;

use Laravarc\Authorizer\Fluent\PendingRoleCreate;
use Laravarc\Authorizer\Services\AuthorizationService;

/**
 * Fluent role factory: Role::create('Accounting')->tenant($id)->grant(...)
 *
 * Distinct from the internal Eloquent {@see \Laravarc\Authorizer\Models\Role}.
 */
final class RoleFactory
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function create(string $name): PendingRoleCreate
    {
        return new PendingRoleCreate($this->authorization, $name);
    }
}
