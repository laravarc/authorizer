<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Internal Eloquent model — not the public Role::create() fluent API.
 *
 * @property int $id
 * @property string $uuid
 * @property int|string|null $tenant_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property bool $is_super
 */
final class Role extends Model
{
    protected $table = 'larc_roles';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'description',
        'is_system',
        'is_super',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            if ($role->uuid === null || $role->uuid === '') {
                $role->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_super' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', (string) $tenantId);
    }

    /**
     * @return BelongsToMany<Ability, $this>
     */
    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'larc_role_abilities', 'role_id', 'ability_id');
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function users(): BelongsToMany
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('authorizer.user_model');

        return $this->belongsToMany(
            $userModel,
            'larc_user_roles',
            'role_id',
            'user_id',
        );
    }
}
