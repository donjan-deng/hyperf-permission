# Hyperf权限管理组件

[spatie/laravel-permission](https://github.com/spatie/laravel-permission) 的Hyperf版

## 与spatie/laravel-permission的区别

 - 无中间件,Hyperf目前还没有Auth组件，请自行创建中间件
 - 命令行只保留清除缓存
   ```
   php bin/hyperf.php permission:cache-reset
   ```
- permissions 增加 parent_id,display_name,url,sort字段，用于生成树形菜单，但不是必填。
   ```
   $user->getMenu(); // 获取当前登录用户的菜单
   Permission::getMenuList();//获取所有的permission，以树形展示
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
$role->permissions()->sync([1,2,3]);
//权限添加到一个角色
$permission->assignRole($role);
$permission->syncRoles($roles);//多个
$permission->roles()->sync([1,2,3]);
//删除权限
$role->revokePermissionTo($permission);
$permission->removeRole($role);
//为用户直接分配权限
$user->givePermissionTo('user-center/user/get');
//为用户分配角色
$user->assignRole('管理员');
$user->assignRole($role->id);
$user->assignRole($role);
$user->assignRole(['管理员', '普通用户']);
$user->roles()->sync([1,2,3]);
//删除角色
$user->removeRole('管理员');
//获取角色集合
$user->getRoleNames();
//获取所有权限
$user->getAllPermissions();
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

### 1.0.1 - 2019-12-17

- 去除错误注释
- 去掉对illuminate/filesystem的依赖

### 1.0.0 - 2019-12-06

- 修改至spatie/laravel-permission 3.3.0

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
