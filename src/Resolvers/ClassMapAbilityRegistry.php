<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Resolvers;

use Illuminate\Filesystem\Filesystem;
use Laravarc\Authorizer\Contracts\AbilityRegistry;
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

        if ($this->files->exists($this->cachePath)) {
            /** @var array{
             *     abilities: array<string, list<string>>,
             *     policies: array<class-string, string>,
             *     models: array<class-string, string>
             * } $cached
             */
            $cached = require $this->cachePath;
            $this->index = $cached;

            return $this->index;
        }

        $this->index = $this->builder->build();

        return $this->index;
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
        $index = $this->builder->build();
        $this->writeCache($index);
        $this->index = $index;

        return $index;
    }

    public function clear(): void
    {
        if ($this->files->exists($this->cachePath)) {
            $this->files->delete($this->cachePath);
        }

        $this->index = null;
    }

    /**
     * @param  array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }  $index
     */
    private function writeCache(array $index): void
    {
        $this->files->ensureDirectoryExists(dirname($this->cachePath));

        $export = var_export($index, true);
        $contents = <<<PHP
<?php

return {$export};

PHP;

        $this->files->put($this->cachePath, $contents, true);
    }
}
