<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravarc\Authorizer\Contracts\AbilityRegistry;
use Laravarc\Authorizer\Models\Ability;
use Laravarc\Authorizer\Resolvers\ClassMapAbilityRegistry;

/**
 * Syncs the ability registry into larc_abilities.
 *
 * Grant expansion stores concrete ability rows (not policy.* wildcards).
 * When sync adds a new ability to a policy that was previously granted to a
 * role, that role does NOT automatically receive the new ability — re-grant
 * or grant the new method explicitly.
 */
final class AbilitySyncService
{
    public function __construct(
        private readonly AbilityRegistry $registry,
    ) {}

    /**
     * @return array{new: list<string>, missing: list<string>}
     */
    public function diff(): array
    {
        $scanned = $this->flatKeys($this->currentAbilities());
        $stored = Ability::query()
            ->get(['policy', 'ability'])
            ->map(static fn (Ability $a): string => $a->policy.'.'.$a->ability)
            ->all();

        $new = array_values(array_diff($scanned, $stored));
        $missing = array_values(array_diff($stored, $scanned));

        sort($new);
        sort($missing);

        return ['new' => $new, 'missing' => $missing];
    }

    /**
     * Insert all scanned abilities that are not yet in the database.
     *
     * @return int Number of rows inserted
     */
    public function insertNew(): int
    {
        return $this->insertKeys($this->diff()['new']);
    }

    /**
     * @param  list<string>  $keys
     * @return int Number of rows inserted
     */
    public function insertKeys(array $keys): int
    {
        $count = 0;

        foreach ($keys as $key) {
            [$policy, $ability] = $this->split($key);

            $exists = Ability::query()
                ->where('policy', $policy)
                ->where('ability', $ability)
                ->exists();

            if ($exists) {
                continue;
            }

            Ability::query()->create([
                'uuid' => (string) Str::uuid(),
                'policy' => $policy,
                'ability' => $ability,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Permanently delete a missing ability row (cascades pivots via FK).
     */
    public function deleteMissing(string $key): void
    {
        [$policy, $ability] = $this->split($key);

        Ability::query()
            ->where('policy', $policy)
            ->where('ability', $ability)
            ->delete();
    }

    /**
     * Rename a missing ability to a new key in-place (same id → pivots stay valid).
     */
    public function replaceMissing(string $missingKey, string $newKey): void
    {
        [$oldPolicy, $oldAbility] = $this->split($missingKey);
        [$newPolicy, $newAbility] = $this->split($newKey);

        $row = Ability::query()
            ->where('policy', $oldPolicy)
            ->where('ability', $oldAbility)
            ->firstOrFail();

        $row->update([
            'policy' => $newPolicy,
            'ability' => $newAbility,
        ]);
    }

    /**
     * @return array{users: int, roles: int, records: int}
     */
    public function assignmentStats(string $key): array
    {
        [$policy, $ability] = $this->split($key);

        $abilityId = Ability::query()
            ->where('policy', $policy)
            ->where('ability', $ability)
            ->value('id');

        if ($abilityId === null) {
            return ['users' => 0, 'roles' => 0, 'records' => 0];
        }

        $roleCount = (int) DB::table('larc_role_abilities')->where('ability_id', $abilityId)->count();
        $userCount = (int) DB::table('larc_user_abilities')->where('ability_id', $abilityId)->count();

        return [
            'users' => $userCount,
            'roles' => $roleCount,
            'records' => $roleCount + $userCount,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function currentAbilities(): array
    {
        if ($this->registry instanceof ClassMapAbilityRegistry) {
            return $this->registry->all();
        }

        return $this->registry->all();
    }

    /**
     * @param  array<string, list<string>>  $abilities
     * @return list<string>
     */
    private function flatKeys(array $abilities): array
    {
        $keys = [];

        foreach ($abilities as $policy => $methods) {
            foreach ($methods as $method) {
                $keys[] = $policy.'.'.$method;
            }
        }

        return $keys;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $key): array
    {
        $pos = strrpos($key, '.');

        if ($pos === false) {
            throw new \InvalidArgumentException("Invalid ability key [{$key}].");
        }

        return [substr($key, 0, $pos), substr($key, $pos + 1)];
    }
}
