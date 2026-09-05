<?php

declare(strict_types=1);

namespace Laravarc\Authorizer;

use Laravarc\Authorizer\Fluent\PendingRoleCreate;
use Laravarc\Authorizer\Fluent\PendingRoleGrant;
use Laravarc\Authorizer\Fluent\PendingUserAuthorization;
use Laravarc\Authorizer\Services\AuthorizationService;

/**
 * Public developer API (prefer this over Eloquent models).
 */
final class Authorization
{
    public function __construct(
        private readonly AuthorizationService $service,
    ) {}

    public function role(string $name): PendingRoleGrant
    {
        return new PendingRoleGrant($this->service, $name);
    }

    /**
     * @param  object{getKey(): mixed}  $user
     */
    public function user(object $user): PendingUserAuthorization
    {
        return new PendingUserAuthorization($this->service, $user);
    }

    public function createRole(string $name): PendingRoleCreate
    {
        return new PendingRoleCreate($this->service, $name);
    }

    public function service(): AuthorizationService
    {
        return $this->service;
    }
}
