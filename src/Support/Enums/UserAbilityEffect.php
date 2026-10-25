<?php

declare(strict_types=1);

namespace Laravarc\Authorizer\Support\Enums;

enum UserAbilityEffect: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
