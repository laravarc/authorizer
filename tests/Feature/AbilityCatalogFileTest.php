<?php

declare(strict_types=1);

use Laravarc\Authorizer\Resolvers\ClassMapAbilityRegistry;
use Laravarc\Authorizer\Support\CachePath;
use Laravarc\Authorizer\Tests\Fixtures\Customer;
use Laravarc\Authorizer\Tests\Fixtures\Policies\CustomerPolicy;

describe('shared ability catalog file', function () {
    $key = 'laravarc.authorizer.tests.fixtures.policies.customer_policy';

    it('writes schema v1 policies and leaves gates empty', function () use ($key) {
        app(ClassMapAbilityRegistry::class)->refresh();

        /** @var array<string, mixed> $data */
        $data = include CachePath::abilities();

        expect($data['schema_version'])->toBe(1)
            ->and($data['generated_by'])->toBe('laravarc authorizer')
            ->and($data['gates'])->toBe([])
            ->and($data['policies'])->toHaveKey($key)
            ->and($data['policies'][$key]['class'])->toBe(CustomerPolicy::class)
            ->and($data['policies'][$key]['models'][0]['abilities'])->toContain('view', 'export');
    });

    it('consumes a core-authored cache without rewriting it', function () use ($key) {
        $path = CachePath::abilities();

        $payload = [
            'schema_version' => 1,
            'compiled_at' => '2026-10-24T00:00:00+00:00',
            'generated_by' => 'laravarc core',
            'policies' => [
                $key => [
                    'class' => CustomerPolicy::class,
                    'models' => [
                        ['class' => Customer::class, 'abilities' => ['view', 'export']],
                    ],
                ],
            ],
            'gates' => [
                Customer::class => ['index' => [[$key.'.view']]],
            ],
        ];

        file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n");
        $before = (string) file_get_contents($path);
        $mtimeBefore = filemtime($path);

        app()->forgetInstance(ClassMapAbilityRegistry::class);
        $registry = app(ClassMapAbilityRegistry::class);
        $index = $registry->refresh();

        clearstatcache(true, $path);
        $mtimeAfter = filemtime($path);

        expect($registry->wasSkippedForCore())->toBeTrue()
            ->and((string) file_get_contents($path))->toBe($before)
            ->and($mtimeAfter)->toBe($mtimeBefore)
            ->and($index['abilities'][$key])->toBe(['export', 'view'])
            ->and($registry->keyFor(Customer::class))->toBe($key);
    });

    it('rejects an unsupported cache schema', function () {
        $path = CachePath::abilities();
        file_put_contents($path, "<?php\n\nreturn ['schema_version' => 99, 'policies' => [], 'gates' => []];\n");

        app()->forgetInstance(ClassMapAbilityRegistry::class);

        expect(fn () => app(ClassMapAbilityRegistry::class)->all())
            ->toThrow(RuntimeException::class, 'unsupported schema_version');
    });

    it('rejects legacy schema v0 without schema_version', function () {
        $path = CachePath::abilities();
        file_put_contents($path, "<?php\n\nreturn ['abilities' => [], 'policies' => [], 'models' => []];\n");

        app()->forgetInstance(ClassMapAbilityRegistry::class);

        expect(fn () => app(ClassMapAbilityRegistry::class)->all())
            ->toThrow(RuntimeException::class, 'laravarc:cache build');
    });
});
