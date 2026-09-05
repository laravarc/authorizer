<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Console\Commands;

use Illuminate\Console\Command;
use Laravarc\Authorizer\Resolvers\ClassMapAbilityRegistry;
use Laravarc\Authorizer\Services\AbilitySyncService;
use Laravarc\Authorizer\Services\AuthorizationService;
use Laravarc\Authorizer\Support\AbilityIndexBuildException;
use Throwable;

final class AuthorizerCommand extends Command
{
    protected $signature = 'laravarc:authorizer
                            {action : install, sync, or cache}
                            {--clear : Remove the cached ability index (cache)}
                            {--role= : Name for the system super role (install; default from config)}
                            {--migrate : Run package migrations (install)}
                            {--dry-run : Preview changes without writing to the database (sync)}
                            {--auto-delete-missing : Non-interactively delete abilities missing from the scan (sync)}
                            {--skip-insert-new : Do not insert newly discovered abilities (sync)}';

    protected $description = 'Authorizer toolkit — install, sync, or cache';

    /** @var list<string> */
    protected $aliases = ['larc:authorizer'];

    public function handle(
        AuthorizationService $authorization,
        AbilitySyncService $sync,
        ClassMapAbilityRegistry $registry,
    ): int {
        return match ((string) $this->argument('action')) {
            'install' => $this->handleInstall($authorization),
            'sync' => $this->handleSync($sync),
            'cache' => $this->handleCache($registry),
            default => $this->invalidAction(),
        };
    }

    private function handleInstall(AuthorizationService $authorization): int
    {
        if ($this->option('migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $roleName = (string) ($this->option('role') ?: config('authorizer.install.super_role_name', 'Owner'));

        try {
            $authorization->createRole(
                name: $roleName,
                description: 'System super role — bypasses all Gate checks via is_super.',
                tenantId: null,
                isSystem: true,
                isSuper: true,
            );
            $this->components->info(sprintf(
                'Created system super role [%s] (is_system=true, is_super=true). Rename freely; bypass uses the flag.',
                $roleName,
            ));
        } catch (\InvalidArgumentException $e) {
            $this->components->warn($e->getMessage());
        }

        return self::SUCCESS;
    }

    private function handleSync(AbilitySyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $diff = $sync->diff();

        $this->components->info(sprintf(
            'Scan diff: %d new, %d missing.',
            count($diff['new']),
            count($diff['missing']),
        ));

        foreach ($diff['new'] as $key) {
            $this->line('  + '.$key);
        }

        foreach ($diff['missing'] as $key) {
            $this->line('  - '.$key);
        }

        if ($dryRun) {
            $this->components->warn('Dry run — no database changes.');

            return self::SUCCESS;
        }

        /** @var list<string> $availableNew */
        $availableNew = $diff['new'];
        $missing = $diff['missing'];
        $total = count($missing);

        foreach ($missing as $index => $key) {
            $n = $index + 1;
            $stats = $sync->assignmentStats($key);

            $this->newLine();
            $this->line(sprintf('[%d/%d] %s — MISSING', $n, $total, $key));
            $this->line(sprintf(
                '      Assigned to: %d users, %d roles (%d permission records)',
                $stats['users'],
                $stats['roles'],
                $stats['records'],
            ));

            if ($this->option('auto-delete-missing')) {
                $sync->deleteMissing($key);
                $this->components->warn(sprintf('Deleted %s (auto-delete-missing).', $key));

                continue;
            }

            if (! $this->input->isInteractive()) {
                $this->components->error(
                    'Missing abilities require interactive resolution or --auto-delete-missing.',
                );

                return self::FAILURE;
            }

            $choice = $this->choice(
                'Action',
                [
                    'Hapus permanen (assignments lose this ability)',
                    'Replace dengan ability baru',
                ],
                0,
            );

            if ($choice === 'Hapus permanen (assignments lose this ability)') {
                if ($this->confirm('Lanjutkan hapus permanen?', false)) {
                    $sync->deleteMissing($key);
                    $this->components->warn(sprintf('Deleted %s.', $key));
                }

                continue;
            }

            if ($availableNew === []) {
                $this->components->error('No unused new abilities available for replace.');

                continue;
            }

            $replacement = $this->choice('Pilih ability pengganti', $availableNew);

            if (! is_string($replacement)) {
                continue;
            }

            if (! $this->confirm(sprintf(
                'Replace will UPDATE this ability row to [%s] (id preserved). Continue?',
                $replacement,
            ), true)) {
                continue;
            }

            $sync->replaceMissing($key, $replacement);
            $availableNew = array_values(array_diff($availableNew, [$replacement]));
            $this->components->info(sprintf('Replaced %s → %s.', $key, $replacement));
        }

        if ($availableNew !== [] && ! $this->option('skip-insert-new')) {
            $shouldInsert = ! $this->input->isInteractive()
                || $this->confirm(
                    sprintf('Insert remaining %d new abilities?', count($availableNew)),
                    true,
                );

            if ($shouldInsert) {
                $count = $sync->insertKeys($availableNew);
                $this->components->info(sprintf('Inserted %d new abilities.', $count));
            }
        }

        $this->components->info('laravarc:authorizer sync complete.');

        return self::SUCCESS;
    }

    private function handleCache(ClassMapAbilityRegistry $registry): int
    {
        if ($this->option('clear')) {
            $registry->clear();
            $this->components->info('Authorizer ability index cache cleared.');

            return self::SUCCESS;
        }

        try {
            $index = $registry->refresh();
            $this->components->info(sprintf(
                'Authorizer ability index cached (%d policies).',
                count($index['abilities']),
            ));

            return self::SUCCESS;
        } catch (AbilityIndexBuildException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function invalidAction(): int
    {
        $this->components->error('Unknown action. Use: install, sync, or cache.');

        return self::FAILURE;
    }
}
