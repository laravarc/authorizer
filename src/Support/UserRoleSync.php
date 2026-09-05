<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Support;

use Illuminate\Support\Facades\DB;
use Laravarc\Authorizer\Models\Role;
use Laravarc\Authorizer\Models\UserRole;

/**
 * Write helpers for larc_user_roles pivot rows.
 */
final class UserRoleSync
{
    /**
     * Replace all global-role assignments for a user with a single role.
     *
     * Tenant-scoped role assignments are left untouched.
     */
    public static function replaceGlobal(int $userId, int $roleId): void
    {
        /** @var list<int> $globalRoleIds */
        $globalRoleIds = Role::query()->global()->pluck('id')->all();

        DB::transaction(function () use ($userId, $roleId, $globalRoleIds): void {
            UserRole::query()
                ->where('user_id', $userId)
                ->whereIn('role_id', $globalRoleIds)
                ->delete();

            static::assign($userId, $roleId);
        });
    }

    /**
     * Attach a user to a role (idempotent).
     */
    public static function assign(int $userId, int $roleId): void
    {
        UserRole::query()->insertOrIgnore([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    /**
     * Remove all role assignments for a user.
     */
    public static function detachAllForUser(int $userId): void
    {
        UserRole::query()->where('user_id', $userId)->delete();
    }
}
