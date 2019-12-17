<?php

declare(strict_types = 1);

namespace Donjan\Permission\Contracts;

use Hyperf\Database\Model\Relations\BelongsToMany;

interface Role
{
    /**
     * A role may be given various permissions.
     */
    public function permissions(): BelongsToMany;

    /**
     * Find a role by its name and guard name.
     */
    public static function findByName(string $name, $guardName): self;

    /**
     * Find a role by its id and guard name.
     */
    public static function findById(int $id, $guardName): self;

    /**
     * Find or create a role by its name and guard name.
     */
    public static function findOrCreate(string $name, $guardName): self;

    /**
     * Determine if the user may perform the given permission.
     */
    public function hasPermissionTo($permission): bool;
}
