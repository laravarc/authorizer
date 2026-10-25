<?php

declare(strict_types=1);

use Laravarc\Authorizer\Support\KeyIdentifier;

describe('KeyIdentifier', function () {
    it('derives fromClass with full basename including suffix', function () {
        expect(KeyIdentifier::fromClass('App\\Modules\\User\\Policies\\JournalPolicy'))
            ->toBe('app.modules.user.policies.journal_policy');
    });

    it('derives fromPath stripping extension only', function () {
        expect(KeyIdentifier::fromPath('Path/To/FileType.php'))
            ->toBe('path.to.file_type');
    });

    it('matches core conformance vectors', function () {
        expect(KeyIdentifier::fromClass('Laravarc\\Authorizer\\Tests\\Fixtures\\Policies\\CustomerPolicy'))
            ->toBe('laravarc.authorizer.tests.fixtures.policies.customer_policy');
    });
});
