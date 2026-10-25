<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Laravarc\Authorizer\Support\CachePath;
use Laravarc\Authorizer\Tests\Fixtures\Customer;
use Laravarc\Authorizer\Tests\Fixtures\Policies\CustomerPolicy;

describe('laravarc:authorizer cache command', function () {
    $key = 'laravarc.authorizer.tests.fixtures.policies.customer_policy';

    it('exits zero with a warning and leaves a core-authored file unchanged', function () use ($key) {
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

        app()->forgetInstance(\Laravarc\Authorizer\Resolvers\ClassMapAbilityRegistry::class);

        $exit = Artisan::call('laravarc:authorizer', ['action' => 'cache']);
        $output = Artisan::output();

        clearstatcache(true, $path);
        $mtimeAfter = filemtime($path);

        expect($exit)->toBe(0)
            ->and($output)->toContain('Core-authored')
            ->and((string) file_get_contents($path))->toBe($before)
            ->and($mtimeAfter)->toBe($mtimeBefore);
    });
});
