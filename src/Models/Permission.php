<?php

declare(strict_types = 1);

namespace Donjan\Permission\Models;

use Hyperf\Utils\ApplicationContext;
use Donjan\Permission\Guard;
use Hyperf\Utils\Collection;
use Donjan\Permission\Traits\HasRoles;
use Hyperf\DbConnection\Model\Model;
use Donjan\Permission\PermissionRegistrar;
use Donjan\Permission\Traits\RefreshesPermissionCache;
use Hyperf\Database\Model\Relations\MorphToMany;
use Donjan\Permission\Exceptions\PermissionDoesNotExist;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Donjan\Permission\Exceptions\PermissionAlreadyExists;
use Donjan\Permission\Contracts\Permission as PermissionContract;

class Permission extends Model implements PermissionContract
{

    use HasRoles;
    use RefreshesPermissionCache;

    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(config('permission.table_names.permissions'));
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        $permission = static::getPermissions(['name' => $attributes['name'], 'guard_name' => $attributes['guard_name']])->first();

        if ($permission) {
            throw PermissionAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * A permission can be applied to roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
                        config('permission.models.role'), config('permission.table_names.role_has_permissions'), 'permission_id', 'role_id'
        );
    }

    /**
     * A permission belongs to some users of the model associated with its guard.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
                        getModelForGuard($this->attributes['guard_name']), 'model', config('permission.table_names.model_has_permissions'), 'permission_id', config('permission.column_names.model_morph_key')
        );
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     */
    public static function findByName(string $name, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions(['name' => $name, 'guard_name' => $guardName])->first();
        if (!$permission) {
            throw PermissionDoesNotExist::create($name, $guardName);
        }

        return $permission;
    }

    /**
     * Find a permission by its id (and optionally guardName).
     *
     */
    public static function findById(int $id, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions(['id' => $id, 'guard_name' => $guardName])->first();

        if (!$permission) {
            throw PermissionDoesNotExist::withId($id, $guardName);
        }

        return $permission;
    }

    /**
     * Find or create permission by its name (and optionally guardName).
     *
     */
    public static function findOrCreate(string $name, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);
        $permission = static::getPermissions(['name' => $name, 'guard_name' => $guardName])->first();

        if (!$permission) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $permission;
    }

    /**
     * 获取树形的permission列表.
     * @param int||string $parentId 父级ID
     * @param bool $isUrl 是否是一个URL
     * @param Collection $permission 传入permission集合，如果不传将从所有的permission生成
     * @return Collection
     */
    public static function getMenuList($parentId = 0, $isUrl = false, Collection $permission = null)
    {
        is_int($parentId) && $parentId = "$parentId";
        !$permission && $permission = self::getPermissions();
        $menus = $permission->where('parent_id', $parentId)->sortByDesc('sort')->values();
        if ($isUrl) {
            $menus = $menus->filter(function($value, $key) {
                return !empty($value->url);
            });
        }
        foreach ($menus as $menu) {
            $menu['child'] = self::getMenuList($menu['id'], $isUrl, $permission);
        }
        return $menus;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getPermissions(array $params = []): Collection
    {
        return ApplicationContext::getContainer()->get(PermissionRegistrar::class)
                        ->setPermissionClass(static::class)
                        ->getPermissions($params);
    }

}
