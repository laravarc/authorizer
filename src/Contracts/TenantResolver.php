<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Contracts;

interface TenantResolver
{
    /**
     * Current tenant id, or null when tenant scoping is inactive (single-tenant).
     */
    public function current(): int|string|null;
}
