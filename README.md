# Hyperf权限管理组件

```diff
+ 推荐使用Hyperf Casbin:  https://github.com/donjan-deng/hyperf-casbin
```

转换至Casbin示例代码，按自己实际情况修改：

```
        $roles = Role::with(['users', 'perms'])->get();
        foreach ($roles as $role) {
            if ($role->perms) {
                foreach ($role->perms as $perm) {
                    Enforcer::addPermissionForUser($role->name, $perm->name, 'ANY');
                }
            }
            if ($role->users) {
                foreach ($role->users as $user) {
                    Enforcer::addRoleForUser($user->username, $role->name);
                }
            }
        }
```

适配Hyperf的[spatie/laravel-permission](https://github.com/spatie/laravel-permission)

使用示例代码：<https://github.com/donjan-deng/la-user-center>

## 与spatie/laravel-permission的区别

 - 无中间件,Hyperf目前还没有Auth组件，请自行创建中间件
 - 命令行只保留清除缓存
   ```
   php bin/hyperf.php permission:cache-reset
   ```
- permissions 增加 parent_id,display_name,url,sort字段，用于生成树形菜单，但不是必填。
   ```
   $user->getMenu(); // 获取当前登录用户的菜单,即url有值。
    /**
     * 获取树形的permission列表.
     * @param int||string $parentId 父级ID
     * @param bool $isUrl 是否是一个URL
     * @param Collection $permission 传入permission集合，如果不传将从所有的permission生成
     * @return Collection
     */
   Permission::getMenuList($parentId = 0, $isUrl = false, Collection $permission = null);
   ```
- roles增加description字段,非必填

## 安装

 ```
  composer require donjan-deng/hyperf-permission
 ```
发布配置
```
 php bin/hyperf.php vendor:publish donjan-deng/hyperf-permission
```
修改配置文件config/autoload/permission.php

数据库迁移

```
php bin/hyperf.php migrate
```
将Donjan\Permission\Traits\HasRoles添加到你的用户Model

```
...
use Donjan\Permission\Traits\HasRoles;

class User extends Model {
    
    use HasRoles;
   ...
}
```
## 使用

```diff
- 使用组件方法进行权限操作，不要使用Eloquent ORM的功能直接进行数据库操作。
```

```
use Donjan\Permission\Models\Permission;
use Donjan\Permission\Models\Role;

//创建一个角色
$role = Role::create(['name' => '管理员','description'=>'']);
//创建权限
$permission = Permission::create(['name' => 'user-center/user/get','display_name'=>'用户管理','url'=>'user-center/user']);
$permission = Permission::create(['name' => 'user-center/user/post','display_name'=>'创建用户','parent_id'=>$p1->id]);
//为角色分配一个权限
$role->givePermissionTo($permission);
$role->syncPermissions($permissions);//多个
$role->syncPermissions([1,2,3]);
//权限添加到一个角色
$permission->assignRole($role);
$permission->syncRoles($roles);//多个
$permission->syncRoles([1,2,3]);
//删除权限
$role->revokePermissionTo($permission);
$permission->removeRole($role);
//为用户直接分配权限
$user->givePermissionTo('user-center/user/get');
//为用户分配角色
$user->assignRole('管理员');
$user->assignRole($role->id);
$user->assignRole($role);
$user->syncRoles(['管理员', '普通用户']);
$user->syncRoles([1,2,3]);
//删除角色
$user->removeRole('管理员');
//获取用户集合
$permission->users;
$role->users;
//获取角色集合
$user->getRoleNames();
$permission->roles;
//获取所有权限
$user->getAllPermissions();
$role->permissions;
//获取树形菜单
$user->getMenu();
//验证
$user->can('user-center/user/get');
$user->can($permission->id);
$user->can($permission);
$user->hasAnyPermission([$permission1,$permission2]);
$user->hasAnyPermission(['user-center/user/get','user-center/user/post']);
$user->hasAnyPermission([1,2]);
$user->hasRole('管理员');
$user->hasRole(['管理员','普通用户']);
$user->hasRole($role);
$user->hasRole([$role1,$role2]);
```

## CHANGELOG

[CHANGELOG.md](CHANGELOG.md)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
