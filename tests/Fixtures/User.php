<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravarc\Authorizer\Concerns\HasAuthorizerAbilities;

final class User extends Authenticatable
{
    use HasAuthorizerAbilities;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
