<?php

declare(strict_types = 1);

namespace Donjan\Permission\Traits;

use Donjan\Permission\PermissionRegistrar;
use Hyperf\Database\Model\Events\Saved;
use Hyperf\Database\Model\Events\Deleted;
use Hyperf\Utils\ApplicationContext;

trait RefreshesPermissionCache
{
    public function saved(Saved $event)
    {
        ApplicationContext::getContainer()->get(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function deleted(Deleted $event)
    {
        ApplicationContext::getContainer()->get(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
