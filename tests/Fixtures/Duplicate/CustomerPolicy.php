<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Tests\Fixtures\Duplicate;

/**
 * Shares the class basename with Policies\CustomerPolicy but resolves to a
 * distinct FQCN-based ability key.
 */
final class CustomerPolicy
{
    public function view(): bool
    {
        return true;
    }
}
