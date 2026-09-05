<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('larc_abilities', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('larc_abilities', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
