<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Fluent;

use Laravarc\Authorizer\Models\Role;
use Laravarc\Authorizer\Services\AuthorizationService;

/**
 * Fluent entry: Role::create('Accounting')->tenant($id)->grant(Policy::class)->only([...])
 */
final class PendingRoleCreate
{
    private int|string|null $tenantId = null;

    private ?string $description = null;

    private bool $created = false;

    private ?Role $role = null;

    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly string $name,
    ) {}

    public function tenant(int|string|null $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  class-string  $policyClass
     */
    public function grant(string $policyClass): PendingGrant
    {
        $role = $this->ensureCreated();

        return new PendingGrant($this->authorization, $role, $policyClass);
    }

    public function get(): Role
    {
        return $this->ensureCreated();
    }

    private function ensureCreated(): Role
    {
        if ($this->created && $this->role !== null) {
            return $this->role;
        }

        $this->role = $this->authorization->createRole(
            name: $this->name,
            description: $this->description,
            tenantId: $this->tenantId,
        );
        $this->created = true;

        return $this->role;
    }
}
