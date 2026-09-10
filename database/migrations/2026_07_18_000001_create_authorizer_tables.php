<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('larc_abilities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('policy');
            $table->string('ability');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['policy', 'ability']);
        });

        Schema::create('larc_roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_super')->default(false);
            $table->timestamps();

            // UNIQUE(name, tenant_id) alone is insufficient: SQL NULLs are not equal.
            // Uniqueness for NULL tenant_id is enforced in RoleService before insert.
            $table->index(['name', 'tenant_id']);
        });

        Schema::create('larc_role_abilities', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('larc_roles')->cascadeOnDelete();
            $table->foreignId('ability_id')->constrained('larc_abilities')->cascadeOnDelete();

            $table->primary(['role_id', 'ability_id']);
        });

        Schema::create('larc_user_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id');
            $table->foreignId('role_id')->constrained('larc_roles')->cascadeOnDelete();

            $table->primary(['user_id', 'role_id']);
            $table->index('user_id');
        });

        Schema::create('larc_user_abilities', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id');
            $table->foreignId('ability_id')->constrained('larc_abilities')->cascadeOnDelete();

            $table->primary(['user_id', 'ability_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('larc_user_abilities');
        Schema::dropIfExists('larc_user_roles');
        Schema::dropIfExists('larc_role_abilities');
        Schema::dropIfExists('larc_roles');
        Schema::dropIfExists('larc_abilities');
    }
};
