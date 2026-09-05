<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Support;

use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Throwable;

/**
 * Discovers Policy classes and builds ability_key => methods (+ class maps).
 *
 * Native convention: CustomerPolicy → model Customer → key "customer".
 * Does not use module namespaces (Core-specific).
 */
final class AbilityIndexBuilder
{
    /**
     * @param  list<string>  $scanPaths
     * @param  list<string>  $scanNamespaces
     * @param  list<string>  $excludeNamespaces
     * @param  list<class-string>  $excludeClasses
     * @param  list<class-string>  $includeClasses
     */
    public function __construct(
        private readonly array $scanPaths = [],
        private readonly array $scanNamespaces = [],
        private readonly array $excludeNamespaces = [],
        private readonly array $excludeClasses = [],
        private readonly array $includeClasses = [],
    ) {}

    /**
     * @return array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }
     */
    public function build(): array
    {
        return $this->buildFrom($this->discoverClasses());
    }

    /**
     * @param  list<class-string>  $classes
     * @return array{
     *     abilities: array<string, list<string>>,
     *     policies: array<class-string, string>,
     *     models: array<class-string, string>
     * }
     */
    public function buildFrom(array $classes): array
    {
        /** @var array<string, list<string>> $abilities */
        $abilities = [];
        /** @var array<class-string, string> $policies */
        $policies = [];
        /** @var array<class-string, string> $models */
        $models = [];
        /** @var array<string, class-string> $keyOwners */
        $keyOwners = [];

        foreach ($classes as $class) {
            if (! $this->isPolicyClass($class)) {
                continue;
            }

            $key = $this->abilityKeyFromPolicy($class);

            if (isset($keyOwners[$key]) && $keyOwners[$key] !== $class) {
                throw new AbilityIndexBuildException(sprintf(
                    'Duplicate ability key [%s] registered by [%s] and [%s].',
                    $key,
                    $keyOwners[$key],
                    $class,
                ));
            }

            $keyOwners[$key] = $class;
            $methods = $this->publicAbilityMethods($class);
            $abilities[$key] = $methods;
            $policies[$class] = $key;

            $modelClass = $this->guessModelClass($class);
            if ($modelClass !== null) {
                $models[$modelClass] = $key;
            }
        }

        ksort($abilities);

        return [
            'abilities' => $abilities,
            'policies' => $policies,
            'models' => $models,
        ];
    }

    /**
     * @param  class-string  $policyClass
     */
    public function abilityKeyFromPolicy(string $policyClass): string
    {
        $short = class_basename($policyClass);

        if (str_ends_with($short, 'Policy')) {
            $short = substr($short, 0, -strlen('Policy'));
        }

        return Str::snake($short);
    }

    /**
     * @param  class-string  $policyClass
     * @return list<string>
     */
    public function publicAbilityMethods(string $policyClass): array
    {
        $reflection = new ReflectionClass($policyClass);
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $policyClass) {
                continue;
            }

            $name = $method->getName();

            if ($name === '__construct' || str_starts_with($name, '__')) {
                continue;
            }

            $methods[] = $name;
        }

        sort($methods);

        return $methods;
    }

    /**
     * @return list<class-string>
     */
    private function discoverClasses(): array
    {
        $classes = [];

        foreach ($this->resolveScanTargets() as $target) {
            foreach ($this->scanDirectory($target['path'], $target['namespace']) as $class) {
                if ($this->shouldIncludeClass($class)) {
                    $classes[] = $class;
                }
            }
        }

        foreach ($this->includeClasses as $class) {
            if ($this->shouldIncludeClass($class) && $this->isLoadableClass($class)) {
                $classes[] = $class;
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * @return list<array{namespace: string, path: string}>
     */
    private function resolveScanTargets(): array
    {
        /** @var array<string, list<string>> $namespacePaths */
        $namespacePaths = [];

        foreach ($this->scanPaths as $root) {
            $root = rtrim($root, '/');

            foreach ($this->psr4FromComposerJson($root) as $namespace => $paths) {
                foreach ($paths as $path) {
                    $namespacePaths[$namespace][] = $path;
                }
            }

            foreach ($this->psr4FromAutoloadFile($root) as $namespace => $paths) {
                foreach ($paths as $path) {
                    if ($this->shouldAcceptAutoloadPath($namespace, $path)) {
                        $namespacePaths[$namespace][] = $path;
                    }
                }
            }
        }

        $targets = [];

        foreach ($namespacePaths as $namespace => $paths) {
            $normalizedNamespace = rtrim($namespace, '\\');

            if ($this->isExcludedNamespace($normalizedNamespace)) {
                continue;
            }

            foreach (array_unique($paths) as $path) {
                if ($this->shouldSkipScanPath($path) && ! $this->namespaceExplicitlyConfigured($normalizedNamespace)) {
                    continue;
                }

                $targets[] = [
                    'namespace' => $normalizedNamespace,
                    'path' => $path,
                ];
            }
        }

        return $targets;
    }

    /**
     * @return array<string, list<string>>
     */
    private function psr4FromComposerJson(string $root): array
    {
        $composerFile = $root.'/composer.json';

        if (! is_file($composerFile)) {
            return [];
        }

        /** @var array<string, mixed>|null $composer */
        $composer = json_decode((string) file_get_contents($composerFile), true);

        if (! is_array($composer)) {
            return [];
        }

        $mappings = [];

        foreach (['autoload', 'autoload-dev'] as $section) {
            $psr4 = $composer[$section]['psr-4'] ?? null;

            if (! is_array($psr4)) {
                continue;
            }

            foreach ($psr4 as $namespace => $paths) {
                if (! is_string($namespace)) {
                    continue;
                }

                foreach ((array) $paths as $path) {
                    if (! is_string($path) || $path === '') {
                        continue;
                    }

                    $absolute = $root.'/'.trim(str_replace('\\', '/', $path), '/');
                    $mappings[$namespace][] = $absolute;
                }
            }
        }

        return $mappings;
    }

    /**
     * @return array<string, list<string>>
     */
    private function psr4FromAutoloadFile(string $root): array
    {
        $autoloadPath = $root.'/vendor/composer/autoload_psr4.php';

        if (! is_file($autoloadPath)) {
            return [];
        }

        /** @var array<string, list<string>> $psr4 */
        $psr4 = require $autoloadPath;

        return $psr4;
    }

    private function shouldAcceptAutoloadPath(string $namespace, string $path): bool
    {
        $normalizedNamespace = rtrim($namespace, '\\');

        if ($this->namespaceExplicitlyConfigured($normalizedNamespace)) {
            return true;
        }

        return ! $this->shouldSkipScanPath($path);
    }

    private function namespaceExplicitlyConfigured(string $namespace): bool
    {
        foreach ($this->scanNamespaces as $configured) {
            $configured = rtrim($configured, '\\');

            if ($namespace === $configured
                || str_starts_with($namespace.'\\', $configured.'\\')
                || str_starts_with($configured.'\\', $namespace.'\\')) {
                return true;
            }
        }

        return false;
    }

    private function isExcludedNamespace(string $namespace): bool
    {
        foreach ($this->excludeNamespaces as $excluded) {
            $excluded = rtrim($excluded, '\\');

            if ($namespace === $excluded || str_starts_with($namespace.'\\', $excluded.'\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string  $class
     */
    private function shouldIncludeClass(string $class): bool
    {
        if (in_array($class, $this->excludeClasses, true)) {
            return false;
        }

        foreach ($this->excludeNamespaces as $excluded) {
            $excluded = rtrim($excluded, '\\');

            if ($class === $excluded || str_starts_with($class, $excluded.'\\')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<class-string>
     */
    private function scanDirectory(string $directory, string $namespace): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (! str_ends_with($file->getFilename(), 'Policy.php')) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1);
            $class = $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

            if (! $this->isLoadableClass($class, $file->getPathname())) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }

    /**
     * @param  class-string|string  $class
     */
    private function isLoadableClass(string $class, ?string $path = null): bool
    {
        if (class_exists($class, false)) {
            return true;
        }

        set_error_handler(static function (int $severity, string $message): bool {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            if ($path !== null && is_file($path)) {
                require_once $path;

                return class_exists($class, false);
            }

            return class_exists($class);
        } catch (Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param  class-string  $class
     */
    private function isPolicyClass(string $class): bool
    {
        if (! $this->isLoadableClass($class)) {
            return false;
        }

        $short = class_basename($class);

        return str_ends_with($short, 'Policy');
    }

    /**
     * @param  class-string  $policyClass
     * @return class-string|null
     */
    private function guessModelClass(string $policyClass): ?string
    {
        $short = class_basename($policyClass);

        if (! str_ends_with($short, 'Policy')) {
            return null;
        }

        $modelShort = substr($short, 0, -strlen('Policy'));
        $namespace = (new ReflectionClass($policyClass))->getNamespaceName();

        $candidates = [];

        if (str_ends_with($namespace, '\\Policies')) {
            $root = substr($namespace, 0, -strlen('\\Policies'));
            $candidates[] = $root.'\\Models\\'.$modelShort;
            $candidates[] = $root.'\\'.$modelShort;
        }

        $candidates[] = $namespace.'\\'.$modelShort;

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function shouldSkipScanPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return str_contains($normalized, '/vendor/')
            || str_contains($normalized, '/node_modules/')
            || str_ends_with($normalized, '/vendor')
            || str_ends_with($normalized, '/node_modules');
    }
}
