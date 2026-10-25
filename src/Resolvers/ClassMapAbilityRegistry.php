<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Resolvers;

use Illuminate\Filesystem\Filesystem;
use Laravarc\Authorizer\Contracts\AbilityRegistry;
use Laravarc\Authorizer\Support\AbilityCatalogFile;
use Laravarc\Authorizer\Support\AbilityIndexBuilder;

final class ClassMapAbilityRegistry implements AbilityRegistry
{
    /**
     * @var array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }|null
     */
    private ?array $index = null;

    private bool $skippedForCore = false;

    public function __construct(
        private readonly AbilityIndexBuilder $builder,
        private readonly Filesystem $files,
        private readonly string $cachePath,
    ) {}

    public function all(): array
    {
        return $this->index()['abilities'];
    }

    public function keyFor(string $class): ?string
    {
        $index = $this->index();

        return $index['policies'][$class]
            ?? $index['models'][$class]
            ?? null;
    }

    public function has(string $policyKey, string $ability): bool
    {
        $methods = $this->all()[$policyKey] ?? null;

        return is_array($methods) && in_array($ability, $methods, true);
    }

    public function wasSkippedForCore(): bool
    {
        return $this->skippedForCore;
    }

    /**
     * @return array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }
     */
    public function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $catalog = new AbilityCatalogFile($this->cachePath);
        $data = $catalog->read();

        if ($data !== null) {
            return $this->index = $this->catalogToIndex($data);
        }

        return $this->index = $this->builder->build();
    }

    /**
     * @return array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }
     */
    public function refresh(): array
    {
        $built = $this->builder->build();
        $catalog = new AbilityCatalogFile($this->cachePath);

        if ($catalog->isCoreAuthored()) {
            $this->skippedForCore = true;
            $data = $catalog->read() ?? [];

            return $this->index = $this->catalogToIndex($data);
        }

        $catalog->writePolicies($this->indexToCatalog($built));
        $data = $catalog->read() ?? [];

        return $this->index = $this->catalogToIndex($data);
    }

    public function clear(): void
    {
        (new AbilityCatalogFile($this->cachePath))->clear();

        $this->index = null;
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }
     */
    private function catalogToIndex(array $catalog): array
    {
        $abilities = [];
        $policies = [];
        $models = [];

        foreach (($catalog['policies'] ?? []) as $policyKey => $policy) {
            if (! is_array($policy)) {
                continue;
            }

            $methods = [];

            foreach (($policy['models'] ?? []) as $model) {
                if (! is_array($model)) {
                    continue;
                }

                foreach (($model['abilities'] ?? []) as $ability) {
                    if (is_string($ability) && $ability !== '') {
                        $methods[$ability] = true;
                    }
                }

                if (isset($model['class']) && is_string($model['class']) && $model['class'] !== '') {
                    $models[$model['class']] = (string) $policyKey;
                }
            }

            if (isset($policy['class']) && is_string($policy['class']) && $policy['class'] !== '') {
                $policies[$policy['class']] = (string) $policyKey;
            }

            $methodList = array_keys($methods);
            sort($methodList);
            $abilities[(string) $policyKey] = $methodList;
        }

        ksort($abilities);

        return [
            'abilities' => $abilities,
            'policies' => $policies,
            'models' => $models,
        ];
    }

    /**
     * @param  array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }  $index
     * @return array<string, array<string, mixed>>
     */
    private function indexToCatalog(array $index): array
    {
        $catalog = [];

        foreach ($index['abilities'] as $policyKey => $methods) {
            $policyClass = array_search($policyKey, $index['policies'], true);
            $modelClasses = array_keys($index['models'], $policyKey, true);

            if ($modelClasses === [] && is_string($policyClass)) {
                $modelClasses = [$policyClass];
            }

            $models = [];

            foreach ($modelClasses as $modelClass) {
                $models[] = [
                    'class' => (string) $modelClass,
                    'abilities' => array_values($methods),
                ];
            }

            $catalog[(string) $policyKey] = [
                'class' => is_string($policyClass) ? $policyClass : null,
                'models' => $models,
            ];
        }

        ksort($catalog);

        return $catalog;
    }
}
