<?php

declare(strict_types = 1);

namespace Donjan\Permission\Exceptions;

use InvalidArgumentException;
use Hyperf\Utils\Collection;

class GuardDoesNotMatch extends InvalidArgumentException
{
    public static function create(string $givenGuard, Collection $expectedGuards)
    {
        return new static("The given role or permission should use guard `{$expectedGuards->implode(', ')}` instead of `{$givenGuard}`.");
    }
}
