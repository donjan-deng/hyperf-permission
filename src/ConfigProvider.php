<?php

declare(strict_types = 1);

namespace Donjan\Permission;

use Hyperf\Utils\Collection;
use Hyperf\Utils\Filesystem\Filesystem;
use Donjan\Permission\Commands\CacheReset;

class ConfigProvider
{

    public function __invoke(): array
    {
        return [
            'commands' => [
                CacheReset::class
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for permission.',
                    'source' => __DIR__ . '/../publish/permission.php',
                    'destination' => BASE_PATH . '/config/autoload/permission.php',
                ],
                [
                    'id' => 'database',
                    'description' => 'The database for permission.',
                    'source' => __DIR__ . '/../database/migrations/create_permission_tables.php.stub',
                    'destination' => $this->getMigrationFileName(),
                ]
            ],
        ];
    }

    protected function getMigrationFileName(): string
    {
        $timestamp = date('Y_m_d_His');
        $filesystem = new Filesystem();
        return Collection::make(BASE_PATH . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
                        ->flatMap(function ($path) use ($filesystem) {
                            return $filesystem->glob($path . '*_create_permission_tables.php');
                        })->push(BASE_PATH . "/migrations/{$timestamp}_create_permission_tables.php")
                        ->first();
    }

}
