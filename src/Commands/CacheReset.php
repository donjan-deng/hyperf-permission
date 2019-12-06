<?php

declare(strict_types = 1);

namespace Donjan\Permission\Commands;

use Donjan\Permission\PermissionRegistrar;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Utils\ApplicationContext;

class CacheReset extends HyperfCommand
{

    protected $name = 'permission:cache-reset';

    public function __construct()
    {
        parent::__construct('permission:cache-reset');
        $this->setDescription('Reset the permission cache');
    }

    public function handle()
    {
        if (ApplicationContext::getContainer()->get(PermissionRegistrar::class)->forgetCachedPermissions()) {
            $this->line('Permission cache flushed.');
        } else {
            $this->line('Unable to flush cache.');
        }
    }

}
