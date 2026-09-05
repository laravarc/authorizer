<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $policy
 * @property string $ability
 * @property string|null $description
 */
final class Ability extends Model
{
    protected $table = 'larc_abilities';

    protected $fillable = [
        'uuid',
        'policy',
        'ability',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ability): void {
            if ($ability->uuid === null || $ability->uuid === '') {
                $ability->uuid = (string) Str::uuid();
            }
        });
    }

    public function key(): string
    {
        return $this->policy.'.'.$this->ability;
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'larc_role_abilities', 'ability_id', 'role_id');
    }
}
