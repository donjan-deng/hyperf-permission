<?php

declare(strict_types = 1);

namespace Donjan\Permission\Traits;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Collection;
use Donjan\Permission\Contracts\Role;
use Hyperf\Database\Model\Builder;
use Donjan\Permission\PermissionRegistrar;
use Hyperf\Database\Model\Relations\MorphToMany;
use Hyperf\Database\Model\Events\Deleting;

trait HasRoles
{

    use HasPermissions;

    private $roleClass;

    public function deleting(Deleting $event)
    {
        if (method_exists($this, 'isForceDeleting') && !$this->isForceDeleting()) {
            return;
        }
        $this->roles()->detach();
    }

    public function getRoleClass()
    {
        if (!isset($this->roleClass)) {
            $this->roleClass = ApplicationContext::getContainer()->get(PermissionRegistrar::class)->getRoleClass();
        }

        return $this->roleClass;
    }

    /**
     * A model may have multiple roles.
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
                        config('permission.models.role'), 'model', config('permission.table_names.model_has_roles'), config('permission.column_names.model_morph_key'), 'role_id'
        );
    }

    /**
     * Scope the model query to certain roles only.
     */
    public function scopeRole(Builder $query, $roles, $guard = null): Builder
    {
        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $roles = array_map(function ($role) use ($guard) {
            if ($role instanceof Role) {
                return $role;
            }

            $method = is_numeric($role) ? 'findById' : 'findByName';
            $guard = $guard ?: $this->getDefaultGuardName();

            return $this->getRoleClass()->{$method}($role, $guard);
        }, $roles);

        return $query->whereHas('roles', function ($query) use ($roles) {
                    $query->where(function ($query) use ($roles) {
                        foreach ($roles as $role) {
                            $query->orWhere(config('permission.table_names.roles') . '.id', $role->id);
                        }
                    });
                });
    }

    /**
     * Assign the given role to the model.
     *
     * @return $this
     */
    public function assignRole(...$roles)
    {
        $roles = collect($roles)
                ->flatten(1)
                ->map(function ($role) {
                    if (empty($role)) {
                        return false;
                    }

                    return $this->getStoredRole($role);
                })
                ->filter(function ($role) {
                    return $role instanceof Role;
                })
                ->each(function ($role) {
                    $this->ensureModelSharesGuard($role);
                })
                ->map->id
                ->all();
        $model = $this->getModel();
        $this->roles()->sync($roles, false);
        $model->load('roles');
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Revoke the given role from the model.
     */
    public function removeRole($role)
    {
        $this->roles()->detach($this->getStoredRole($role));

        $this->load('roles');

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     *
     * @return $this
     */
    public function syncRoles(...$roles)
    {
        $this->roles()->detach();

        return $this->assignRole($roles);
    }

    /**
     * Determine if the model has (one of) the given role(s).
     *
     * @return bool
     */
    public function hasRole($roles, string $guard = null): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $guard ? $this->roles->where('guard_name', $guard)->contains('name', $roles) : $this->roles->contains('name', $roles);
        }

        if (is_int($roles)) {
            return $guard ? $this->roles->where('guard_name', $guard)->contains('id', "$roles") : $this->roles->contains('id', "$roles");
        }

        if ($roles instanceof Role) {
            return $this->roles->contains('id', "{$roles->id}");
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }

            return false;
        }

        return $roles->intersect($guard ? $this->roles->where('guard_name', $guard) : $this->roles)->isNotEmpty();
    }

    /**
     * Determine if the model has any of the given role(s).
     * @return bool
     */
    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the model has all of the given role(s).
     *
     * @return bool
     */
    public function hasAllRoles($roles, string $guard = null): bool
    {
        if (is_string($roles) && false !== strpos($roles, '|')) {
            $roles = $this->convertPipeToArray($roles);
        }

        if (is_string($roles)) {
            return $guard ? $this->roles->where('guard_name', $guard)->contains('name', $roles) : $this->roles->contains('name', $roles);
        }

        if ($roles instanceof Role) {
            return $this->roles->contains('id', $roles->id);
        }

        $roles = collect()->make($roles)->map(function ($role) {
            return $role instanceof Role ? $role->name : $role;
        });

        return $roles->intersect(
                        $guard ? $this->roles->where('guard_name', $guard)->pluck('name') : $this->getRoleNames()) == $roles;
    }

    /**
     * Return all permissions directly coupled to the model.
     */
    public function getDirectPermissions(): Collection
    {
        return $this->permissions;
    }

    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    protected function getStoredRole($role): Role
    {
        $roleClass = $this->getRoleClass();

        if (is_numeric($role)) {
            return $roleClass->findById($role, $this->getDefaultGuardName());
        }

        if (is_string($role)) {
            return $roleClass->findByName($role, $this->getDefaultGuardName());
        }

        return $role;
    }

    protected function convertPipeToArray(string $pipeString)
    {
        $pipeString = trim($pipeString);

        if (strlen($pipeString) <= 2) {
            return $pipeString;
        }

        $quoteCharacter = substr($pipeString, 0, 1);
        $endCharacter = substr($quoteCharacter, -1, 1);

        if ($quoteCharacter !== $endCharacter) {
            return explode('|', $pipeString);
        }

        if (!in_array($quoteCharacter, ["'", '"'])) {
            return explode('|', $pipeString);
        }

        return explode('|', trim($pipeString, $quoteCharacter));
    }

}
