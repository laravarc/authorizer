<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Support;

use Laravarc\Authorizer\Contracts\TenantResolver;

final class NullTenantResolver implements TenantResolver
{
    public function current(): int|string|null
    {
        return null;
    }
}
