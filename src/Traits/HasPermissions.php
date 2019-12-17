<?php

declare(strict_types = 1);

namespace Donjan\Permission\Traits;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Collection;
use Donjan\Permission\Guard;
use Hyperf\Database\Model\Builder;
use Donjan\Permission\PermissionRegistrar;
use Donjan\Permission\Contracts\Permission;
use Donjan\Permission\Exceptions\GuardDoesNotMatch;
use Hyperf\Database\Model\Relations\MorphToMany;
use Donjan\Permission\Exceptions\PermissionDoesNotExist;
use Hyperf\Database\Model\Events\Deleting;
use Donjan\Permission\Models\Permission as ModelPermission;

trait HasPermissions
{

    private $permissionClass;

    public function deleting(Deleting $event)
    {
        if (method_exists($this, 'isForceDeleting') && !$this->isForceDeleting()) {
            return;
        }
        $this->permissions()->detach();
    }

    public function getPermissionClass()
    {
        if (!isset($this->permissionClass)) {
            $this->permissionClass = ApplicationContext::getContainer()->get(PermissionRegistrar::class)->getPermissionClass();
        }

        return $this->permissionClass;
    }

    /**
     * A model may have multiple direct permissions.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(
                        config('permission.models.permission'), 'model', config('permission.table_names.model_has_permissions'), config('permission.column_names.model_morph_key'), 'permission_id'
        );
    }

    /**
     * Scope the model query to certain permissions only.
     *
     */
    public function scopePermission(Builder $query, $permissions): Builder
    {
        $permissions = $this->convertToPermissionModels($permissions);

        $rolesWithPermissions = array_unique(array_reduce($permissions, function ($result, $permission) {
                    return array_merge($result, $permission->roles->all());
                }, []));

        return $query->where(function ($query) use ($permissions, $rolesWithPermissions) {
                    $query->whereHas('permissions', function ($query) use ($permissions) {
                        $query->where(function ($query) use ($permissions) {
                            foreach ($permissions as $permission) {
                                $query->orWhere(config('permission.table_names.permissions') . '.id', $permission->id);
                            }
                        });
                    });
                    if (count($rolesWithPermissions) > 0) {
                        $query->orWhereHas('roles', function ($query) use ($rolesWithPermissions) {
                            $query->where(function ($query) use ($rolesWithPermissions) {
                                foreach ($rolesWithPermissions as $role) {
                                    $query->orWhere(config('permission.table_names.roles') . '.id', $role->id);
                                }
                            });
                        });
                    }
                });
    }

    /**
     *
     * @return array
     */
    protected function convertToPermissionModels($permissions): array
    {
        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }

        $permissions = is_array($permissions) ? $permissions : [$permissions];

        return array_map(function ($permission) {
            if ($permission instanceof Permission) {
                return $permission;
            }

            return $this->getPermissionClass()->findByName($permission, $this->getDefaultGuardName());
        }, $permissions);
    }

    /**
     * Determine if the model may perform the given permission.
     *
     *
     * @return bool
     * @throws PermissionDoesNotExist
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByName(
                    $permission, $guardName ?? $this->getDefaultGuardName()
            );
        }

        if (is_int($permission)) {
            $permission = $permissionClass->findById(
                    $permission, $guardName ?? $this->getDefaultGuardName()
            );
        }

        if (!$permission instanceof Permission) {
            throw new PermissionDoesNotExist;
        }

        return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
    }

    /**
     * @deprecated since 2.35.0
     */
    public function hasUncachedPermissionTo($permission, $guardName = null): bool
    {
        return $this->hasPermissionTo($permission, $guardName);
    }

    /**
     * An alias to hasPermissionTo(), but avoids throwing an exception.
     *
     *
     * @return bool
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        try {
            return $this->hasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    public function can($permission, $guardName = null): bool
    {
        try {
            return $this->hasPermissionTo($permission, $guardName);
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * Determine if the model has any of the given permissions.
     *
     * @param array ...$permissions
     *
     * @return bool
     * @throws \Exception
     */
    public function hasAnyPermission(...$permissions): bool
    {
        if (is_array($permissions[0])) {
            $permissions = $permissions[0];
        }

        foreach ($permissions as $permission) {
            if ($this->checkPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the model has all of the given permissions.
     *
     * @param array ...$permissions
     *
     * @return bool
     * @throws \Exception
     */
    public function hasAllPermissions(...$permissions): bool
    {
        if (is_array($permissions[0])) {
            $permissions = $permissions[0];
        }

        foreach ($permissions as $permission) {
            if (!$this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the model has, via roles, the given permission.
     *
     *
     * @return bool
     */
    protected function hasPermissionViaRole(Permission $permission): bool
    {
        return $this->hasRole($permission->roles);
    }

    /**
     * Determine if the model has the given permission.
     *
     *
     * @return bool
     * @throws PermissionDoesNotExist
     */
    public function hasDirectPermission($permission): bool
    {
        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByName($permission, $this->getDefaultGuardName());
        }

        if (is_int($permission)) {
            $permission = $permissionClass->findById($permission, $this->getDefaultGuardName());
        }

        if (!$permission instanceof Permission) {
            throw new PermissionDoesNotExist;
        }

        return $this->permissions->contains('id', "{$permission->id}");
    }

    /**
     * Return all the permissions the model has via roles.
     */
    public function getPermissionsViaRoles(): Collection
    {
        return $this->loadMissing('roles', 'roles.permissions')
                ->roles->flatMap(function ($role) {
                    return $role->permissions;
                })->sort()->values();
    }

    /**
     * Return all the permissions the model has, both directly and via roles.
     *
     * @throws \Exception
     */
    public function getAllPermissions(): Collection
    {
        $permissions = $this->permissions;

        if ($this->roles) {
            $permissions = $permissions->merge($this->getPermissionsViaRoles());
        }

        return $permissions->sort()->values();
    }

    public function getMenu(): Collection
    {
        $permission = $this->getAllPermissions();
        $isUrl = true;
        $parentId = 0;
        return ModelPermission::getMenuList($parentId, $isUrl, $permission);
    }

    /**
     * Grant the given permission(s) to a role.
     *
     * @return $this
     */
    public function givePermissionTo(...$permissions)
    {
        $permissions = collect($permissions)
                ->flatten(1)
                ->map(function ($permission) {
                    if (empty($permission)) {
                        return false;
                    }

                    return $this->getStoredPermission($permission);
                })
                ->filter(function ($permission) {
                    return $permission instanceof Permission;
                })
                ->each(function ($permission) {
                    $this->ensureModelSharesGuard($permission);
                })
                ->map->id
                ->all();

        $model = $this->getModel();
        $this->permissions()->sync($permissions, false);
        $model->load('permissions');
        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current permissions and set the given ones.
     *
     *
     * @return $this
     */
    public function syncPermissions(...$permissions)
    {
        $this->permissions()->detach();

        return $this->givePermissionTo($permissions);
    }

    /**
     * Revoke the given permission.
     *
     *
     * @return $this
     */
    public function revokePermissionTo($permission)
    {
        $this->permissions()->detach($this->getStoredPermission($permission));

        $this->forgetCachedPermissions();

        $this->load('permissions');

        return $this;
    }

    public function getPermissionNames(): Collection
    {
        return $this->permissions->pluck('name');
    }

    protected function getStoredPermission($permissions)
    {
        $permissionClass = $this->getPermissionClass();

        if (is_numeric($permissions)) {
            return $permissionClass->findById($permissions, $this->getDefaultGuardName());
        }

        if (is_string($permissions)) {
            return $permissionClass->findByName($permissions, $this->getDefaultGuardName());
        }

        if (is_array($permissions)) {
            return $permissionClass
                            ->whereIn('name', $permissions)
                            ->whereIn('guard_name', $this->getGuardNames())
                            ->get();
        }

        return $permissions;
    }

    protected function ensureModelSharesGuard($roleOrPermission)
    {
//        if (! $this->getGuardNames()->contains($roleOrPermission->guard_name)) {
//            throw GuardDoesNotMatch::create($roleOrPermission->guard_name, $this->getGuardNames());
//        }
    }

    protected function getGuardNames(): Collection
    {
        return Guard::getNames($this);
    }

    protected function getDefaultGuardName(): string
    {
        return Guard::getDefaultName($this);
    }

    /**
     * Forget the cached permissions.
     */
    public function forgetCachedPermissions()
    {
        ApplicationContext::getContainer()->get(PermissionRegistrar::class)->forgetCachedPermissions();
    }

}
