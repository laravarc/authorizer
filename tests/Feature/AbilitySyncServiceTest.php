<?php

declare(strict_types=1);

use Laravarc\Authorizer\Resolvers\ClassMapAbilityRegistry;
use Laravarc\Authorizer\Services\AbilitySyncService;
use Laravarc\Authorizer\Support\CachePath;
use Laravarc\Authorizer\Tests\Fixtures\Customer;
use Laravarc\Authorizer\Tests\Fixtures\Policies\CustomerPolicy;

describe('AbilitySyncService with core-authored catalog', function () {
    it('reports scanned keys from a core fixture as new when the database is empty', function () {
        $key = 'laravarc.authorizer.tests.fixtures.policies.customer_policy';
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
            'gates' => [],
        ];

        file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($payload, true).";\n");

        app()->forgetInstance(ClassMapAbilityRegistry::class);
        app(ClassMapAbilityRegistry::class)->refresh();

        $diff = app(AbilitySyncService::class)->diff();

        expect($diff['new'])->toEqualCanonicalizing([
            $key.'.export',
            $key.'.view',
        ])->and($diff['missing'])->toBe([]);
    });
});
