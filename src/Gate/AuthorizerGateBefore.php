<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Gate;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Laravarc\Authorizer\Contracts\AbilityRegistry;

/**
 * Laravel Gate::before callback.
 *
 * true  → is_super bypass (Policy never runs)
 * false → known ability, user lacks it (Policy never runs)
 * null  → unknown ability OR RBAC pass — continue to Policy business rules
 *
 * AbilityRegistry is resolved per-call so Core can rebind it after boot.
 */
final class AuthorizerGateBefore
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __invoke(?Authenticatable $user, string $ability, array $arguments): ?bool
    {
        if ($user === null) {
            return null;
        }

        if (method_exists($user, 'isSuper') && $user->isSuper()) {
            return true;
        }

        $abilityKey = $this->resolveAbilityKey($ability, $arguments);

        if ($abilityKey === null) {
            return null;
        }

        if (! method_exists($user, 'hasAbility')) {
            return false;
        }

        return $user->hasAbility($abilityKey) ? null : false;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function resolveAbilityKey(string $ability, array $arguments): ?string
    {
        $registry = $this->registry();

        if (str_contains($ability, '.')) {
            [$policy, $method] = $this->split($ability);

            if ($policy !== null && $method !== null && $registry->has($policy, $method)) {
                return $ability;
            }
        }

        $target = $arguments[0] ?? null;

        if ($target === null) {
            return null;
        }

        $class = is_object($target) ? $target::class : (is_string($target) ? $target : null);

        if ($class === null || ! is_string($class)) {
            return null;
        }

        $policyKey = $registry->keyFor($class);

        if ($policyKey === null) {
            return null;
        }

        if (! $registry->has($policyKey, $ability)) {
            return null;
        }

        return $policyKey.'.'.$ability;
    }

    private function registry(): AbilityRegistry
    {
        return $this->container->make(AbilityRegistry::class);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function split(string $abilityKey): array
    {
        $pos = strrpos($abilityKey, '.');

        if ($pos === false) {
            return [null, null];
        }

        return [
            substr($abilityKey, 0, $pos),
            substr($abilityKey, $pos + 1),
        ];
    }
}
