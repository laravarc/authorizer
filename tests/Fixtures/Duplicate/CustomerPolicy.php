<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Tests\Fixtures\Duplicate;

/**
 * Deliberately maps to the same ability key as CustomerPolicy ("customer").
 */
final class CustomerPolicy
{
    public function view(): bool
    {
        return true;
    }
}
