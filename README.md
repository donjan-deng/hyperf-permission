# 此库已停止更新，请尽早迁移至Casbin

<https://github.com/donjan-deng/hyperf-casbin>

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