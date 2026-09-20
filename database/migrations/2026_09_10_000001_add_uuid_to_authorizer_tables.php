<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Upgrade path for installs that ran the 1.0.0 create migration without uuid.
 * Fresh installs already get uuid from create_authorizer_tables — this is a no-op then.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addUuidColumn('larc_abilities');
        $this->addUuidColumn('larc_roles');
    }

    public function down(): void
    {
        $this->dropUuidColumn('larc_roles');
        $this->dropUuidColumn('larc_abilities');
    }

    private function addUuidColumn(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'uuid')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->uuid('uuid')->nullable()->unique()->after('id');
        });

        foreach (DB::table($table)->orderBy('id')->get() as $row) {
            DB::table($table)->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        }
    }

    private function dropUuidColumn(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['uuid']);
            $blueprint->dropColumn('uuid');
        });
    }
};
