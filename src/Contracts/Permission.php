<?php

declare(strict_types = 1);

namespace Donjan\Permission\Contracts;

use Hyperf\Database\Model\Relations\BelongsToMany;

interface Permission
{
    /**
     * A permission can be applied to roles.
     *
     */
    public function roles(): BelongsToMany;

    /**
     * Find a permission by its name.
     *
     */
    public static function findByName(string $name, $guardName): self;

    /**
     * Find a permission by its id.
     *
     */
    public static function findById(int $id, $guardName): self;

    /**
     * Find or Create a permission by its name and guard name.
     *
     */
    public static function findOrCreate(string $name, $guardName): self;
}
