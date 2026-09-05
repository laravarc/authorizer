<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model for larc_user_roles — user ↔ role assignments.
 *
 * @property int $user_id
 * @property int $role_id
 */
final class UserRole extends Model
{
    protected $table = 'larc_user_roles';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role_id',
    ];

    /**
     * Distinct user_id subquery for users assigned to global (NULL tenant) roles.
     *
     * @return Builder<UserRole>
     */
    public static function globalUserIds(): Builder
    {
        return static::query()
            ->select('larc_user_roles.user_id')
            ->joinRole()
            ->whereGlobalRole()
            ->distinct();
    }

    /**
     * @param  Builder<UserRole>  $query
     * @return Builder<UserRole>
     */
    public function scopeJoinRole(Builder $query): Builder
    {
        return $query->join('larc_roles', 'larc_user_roles.role_id', '=', 'larc_roles.id');
    }

    /**
     * Filter to global roles — chain after joinRole().
     *
     * @param  Builder<UserRole>  $query
     * @return Builder<UserRole>
     */
    public function scopeWhereGlobalRole(Builder $query): Builder
    {
        return $query->whereNull('larc_roles.tenant_id');
    }

    /**
     * Filter to tenant-scoped roles — chain after joinRole().
     *
     * @param  Builder<UserRole>  $query
     * @return Builder<UserRole>
     */
    public function scopeWhereTenantRole(Builder $query): Builder
    {
        return $query->whereNotNull('larc_roles.tenant_id');
    }

    /**
     * Filter user-role rows for a user and tenant — chains joinRole().
     *
     * @param  Builder<UserRole>  $query
     * @return Builder<UserRole>
     */
    public function scopeForUserAndTenant(Builder $query, int $userId, int|string $tenantId): Builder
    {
        return $query
            ->joinRole()
            ->where('larc_user_roles.user_id', $userId)
            ->where('larc_roles.tenant_id', (string) $tenantId);
    }

    /**
     * Distinct tenant_id values for roles assigned to a user.
     *
     * @return list<int|string>
     */
    public static function tenantIdsForUser(int $userId): array
    {
        return static::query()
            ->joinRole()
            ->where('larc_user_roles.user_id', $userId)
            ->whereTenantRole()
            ->distinct()
            ->pluck('larc_roles.tenant_id')
            ->map(fn ($id) => is_numeric($id) ? (int) $id : $id)
            ->values()
            ->all();
    }

    public static function userHasTenantRole(int $userId, int|string $tenantId): bool
    {
        return static::query()
            ->forUserAndTenant($userId, $tenantId)
            ->exists();
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
