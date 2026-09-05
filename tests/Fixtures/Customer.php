<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Tests\Fixtures;

final class Customer
{
    public function __construct(public int $id = 1) {}

    public function hasInvoice(): bool
    {
        return false;
    }
}
