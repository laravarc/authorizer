<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Tests\Fixtures\Policies;

use Laravarc\Authorizer\Tests\Fixtures\Customer;
use Laravarc\Authorizer\Tests\Fixtures\User;

final class CustomerPolicy
{
    public static bool $deleteCalled = false;

    public static bool $updateCalled = false;

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        self::$updateCalled = true;

        return true;
    }

    public function delete(User $user, Customer $customer): bool
    {
        self::$deleteCalled = true;

        return ! $customer->hasInvoice();
    }

    public function export(User $user, Customer $customer): bool
    {
        return true;
    }

    public static function reset(): void
    {
        self::$deleteCalled = false;
        self::$updateCalled = false;
    }
}
