<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Fluent;

use Laravarc\Authorizer\Services\AuthorizationService;

/**
 * Authorization::role('Accounting')->grant(Policy::class)->except([...])
 */
final class PendingRoleGrant
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly string $roleName,
    ) {}

    /**
     * @param  class-string  $policyClass
     */
    public function grant(string $policyClass): PendingGrant
    {
        $role = $this->authorization->findRoleOrFail($this->roleName);

        return new PendingGrant($this->authorization, $role, $policyClass);
    }
}
