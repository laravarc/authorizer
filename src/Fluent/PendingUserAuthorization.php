<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Fluent;

use Laravarc\Authorizer\Services\AuthorizationService;

/**
 * Authorization::user($user)->assignRole('Accounting')
 * Authorization::user($user)->allow(Policy::class)->only([...])
 */
final class PendingUserAuthorization
{
    /**
     * @param  object{getKey(): mixed}  $user
     */
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly object $user,
    ) {}

    public function assignRole(string $roleName): self
    {
        $this->authorization->assignRole($this->user, $roleName);

        return $this;
    }

    /**
     * @param  class-string  $policyClass
     */
    public function allow(string $policyClass): PendingUserAllow
    {
        return new PendingUserAllow($this->authorization, $this->user, $policyClass);
    }
}
