<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Support;

use RuntimeException;

/**
 * Shared ability-cache file (bootstrap/cache/laravarc/authorizer-abilities.php).
 *
 * Schema v1: header + `policies` (owned by core or authorizer) + `gates`
 * (owned by core only).
 *
 * Write ownership (locked):
 * - File `generated_by = laravarc core` → authorizer **must not** overwrite (consume only).
 * - File `generated_by = laravarc authorizer` (or missing) → **laravarc:cache build** may
 *   overwrite the whole file, set `generated_by` to `laravarc core`, and add `gates`.
 * Authorizer never generates or mutates `gates` from policy scan; it only preserves an
 * existing `gates` array when the authorizer writes `policies`.
 */
final class AbilityCatalogFile
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly string $path,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        /** @var mixed $data */
        $data = include $this->path;

        if (! is_array($data)) {
            throw new RuntimeException(sprintf('Ability cache [%s] must return an array. Run laravarc:authorizer cache.', $this->path));
        }

        if ((int) ($data['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            throw new RuntimeException(sprintf(
                'Ability cache [%s] has an unsupported schema_version; run laravarc:authorizer cache or laravarc:cache build.',
                $this->path,
            ));
        }

        return $data;
    }

    public function isCoreAuthored(): bool
    {
        $data = $this->read();

        return is_array($data) && ($data['generated_by'] ?? null) === 'laravarc core';
    }

    /**
     * Writes the `policies` section. Returns false without touching the file
     * when core authored it (authorizer must consume, not regenerate).
     *
     * @param  array<string, array<string, mixed>>  $policies
     */
    public function writePolicies(array $policies): bool
    {
        if ($this->isCoreAuthored()) {
            return false;
        }

        $existing = $this->read();
        $gates = is_array($existing['gates'] ?? null) ? $existing['gates'] : [];

        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'compiled_at' => (new \DateTimeImmutable)->format(DATE_ATOM),
            'generated_by' => 'laravarc authorizer',
            'policies' => $policies,
            'gates' => $gates,
        ];

        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create ability cache directory [%s].', $directory));
        }

        $temporary = $this->path.'.'.uniqid('arc-ability-', true).'.tmp';
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n";

        if (file_put_contents($temporary, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write ability cache [%s].', $temporary));
        }

        if (! rename($temporary, $this->path)) {
            @unlink($temporary);

            throw new RuntimeException(sprintf('Unable to publish ability cache [%s].', $this->path));
        }

        return true;
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
