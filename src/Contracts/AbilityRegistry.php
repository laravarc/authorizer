<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Contracts;

/**
 * Stable ability catalog contract.
 *
 * Native mode: ClassMapAbilityRegistry (file scan / cache).
 * Core-enhanced mode: Core rebinds a metadata-backed implementation.
 * Authorizer never reads Core metadata arrays directly.
 */
interface AbilityRegistry
{
    /**
     * @return array<string, list<string>> ability_key => [method1, method2, ...]
     */
    public function all(): array;

    /**
     * Resolve the ability key (e.g. "customer") for a policy or model FQCN.
     *
     * @param  class-string  $class
     */
    public function keyFor(string $class): ?string;

    public function has(string $policyKey, string $ability): bool;
}
