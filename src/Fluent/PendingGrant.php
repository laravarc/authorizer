<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Fluent;

use Laravarc\Authorizer\Models\Role;
use Laravarc\Authorizer\Services\AuthorizationService;

final class PendingGrant
{
    /** @var list<string>|null */
    private ?array $only = null;

    /** @var list<string>|null */
    private ?array $except = null;

    private bool $applied = false;

    /**
     * @param  class-string  $policyClass
     */
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly Role $role,
        private readonly string $policyClass,
    ) {}

    /**
     * @param  list<string>  $abilities
     */
    public function only(array $abilities): self
    {
        $this->only = $abilities;
        $this->apply();

        return $this;
    }

    /**
     * @param  list<string>  $abilities
     */
    public function except(array $abilities): self
    {
        $this->except = $abilities;
        $this->apply();

        return $this;
    }

    public function __destruct()
    {
        if (! $this->applied) {
            $this->apply();
        }
    }

    private function apply(): void
    {
        if ($this->applied) {
            return;
        }

        $this->authorization->grantToRole(
            role: $this->role,
            policyClass: $this->policyClass,
            only: $this->only,
            except: $this->except,
        );

        $this->applied = true;
    }
}
