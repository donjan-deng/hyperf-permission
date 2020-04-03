<?php

namespace Donjan\Permission\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Connectors\ConnectionFactory;
use Hyperf\Database\Connectors\MySqlConnector;
use Hyperf\DbConnection\ConnectionResolver;
use Hyperf\DbConnection\Frequency;
use Hyperf\DbConnection\Pool\PoolFactory;
use Hyperf\Event\EventDispatcher;
use Hyperf\Event\ListenerProvider;
use Hyperf\Framework\Logger\StdoutLogger;
use Psr\Container\ContainerInterface;
use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
//cache
use Hyperf\Cache\CacheManager;
use Hyperf\Cache\Cache;
use Hyperf\Cache\Driver\FileSystemDriver;
use Hyperf\Utils\Packer\PhpSerializerPacker;
use Psr\SimpleCache\CacheInterface;
//
use Hyperf\Utils\ApplicationContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LogLevel;
use Mockery;
use Donjan\Permission\Contracts\Role;
use Donjan\Permission\PermissionRegistrar;
use Donjan\Permission\Contracts\Permission;
use Donjan\Permission\PermissionServiceProvider;
use Donjan\Permission\Test\User;

abstract class TestCase extends BaseTestCase
{

    protected $container;
    protected $config;
    protected $cache;
    protected $testUser;
    protected $testUserRole;
    protected $testUserPermission;

    public function setUp(): void
    {
        parent::setUp();
        $this->container = $this->getContainer();
        $this->config = $this->container->get(ConfigInterface::class);

        $this->getEnvironmentSetUp();
        $this->setUpDatabase();

        $this->testUser = User::first();
        $this->testUserRole = $this->container->get(Role::class)->find(1);
        $this->testUserPermission = $this->container->get(Permission::class)->find(1);
    }

    protected function tearDown()
    {
        Mockery::close();
        Context::set('db.connection.default', null);
    }

    protected function getContainer()
    {
//        $container = Mockery::mock(ContainerInterface::class);
//
//        $factory = new PoolFactory($container);
//        $container->shouldReceive('get')->with(PoolFactory::class)->andReturn($factory);
//
//        $resolver = new ConnectionResolver($container);
//        $container->shouldReceive('get')->with(ConnectionResolverInterface::class)->andReturn($resolver);
//
//        $config = new Config([
//            StdoutLoggerInterface::class => [
//                'log_level' => [
//                    LogLevel::ALERT,
//                    LogLevel::CRITICAL,
//                    LogLevel::DEBUG,
//                    LogLevel::EMERGENCY,
//                    LogLevel::ERROR,
//                    LogLevel::INFO,
//                    LogLevel::NOTICE,
//                    LogLevel::WARNING,
//                ],
//            ],
//            'databases' => [
//                'default' => [
//                    'driver' => env('DB_DRIVER', 'mysql'),
//                    'host' => env('DB_HOST', 'localhost'),
//                    'database' => env('DB_DATABASE', 'hyperf'),
//                    'port' => env('DB_PORT', 3306),
//                    'username' => env('DB_USERNAME', 'root'),
//                    'password' => env('DB_PASSWORD', ''),
//                    'charset' => env('DB_CHARSET', 'utf8'),
//                    'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
//                    'prefix' => env('DB_PREFIX', ''),
//                    'pool' => [
//                        'min_connections' => 1,
//                        'max_connections' => 10,
//                        'connect_timeout' => 10.0,
//                        'wait_timeout' => 3.0,
//                        'heartbeat' => -1,
//                        'max_idle_time' => 60.0,
//                    ],
//                ],
//            ],
//            'cache' => [
//                'default' => [
//                    'driver' => FileSystemDriver::class,
//                    'packer' => PhpSerializerPacker::class,
//                    'prefix' => 'c:',
//                ],
//            ],
//        ]);
//        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturn($config);
//
//        $logger = new StdoutLogger($config);
//        $container->shouldReceive('get')->with(StdoutLoggerInterface::class)->andReturn($logger);
//
//        $connectionFactory = new ConnectionFactory($container);
//        $container->shouldReceive('get')->with(ConnectionFactory::class)->andReturn($connectionFactory);
//
//        $eventDispatcher = new EventDispatcher(new ListenerProvider(), $logger);
//        $container->shouldReceive('get')->with(EventDispatcherInterface::class)->andReturn($eventDispatcher);
//
//        $container->shouldReceive('get')->with('db.connector.mysql')->andReturn(new MySqlConnector());
//        $container->shouldReceive('has')->andReturn(true);
//        $container->shouldReceive('make')->with(Frequency::class)->andReturn(new Frequency());
//        //cache
//        $config = new Config([
//            'cache' => [
//                'default' => [
//                    'driver' => FileSystemDriver::class,
//                    'packer' => PhpSerializerPacker::class,
//                    'prefix' => 'c:',
//                ],
//            ],
//        ]);
//        $logger = Mockery::mock(StdoutLoggerInterface::class);
//        $logger->shouldReceive(Mockery::any())->andReturn(null);
//        $container->shouldReceive('get')->with(CacheManager::class)->andReturn(new CacheManager($config, $logger));
//        $container->shouldReceive('make')->with(FileSystemDriver::class, Mockery::any())->andReturnUsing(function ($class, $args) use ($container) {
//            return new FileSystemDriver($container, $args['config']);
//        });
//        $container->shouldReceive('get')->with(PhpSerializerPacker::class)->andReturn(new PhpSerializerPacker());
//        //$container->shouldReceive('get')->with(CacheInterface::class)->andReturnUsing($container->get(CacheManager::class)->getDriver());
        $container = Mockery::mock(\Hyperf\Di\Container::class);
        $config = new Config([
            'cache' => [
                'default' => [
                    'driver' => FileSystemDriver::class,
                    'packer' => PhpSerializerPacker::class,
                    'prefix' => 'c:',
                ],
            ],
        ]);
        $logger = Mockery::mock(StdoutLoggerInterface::class);
        $logger->shouldReceive(Mockery::any())->andReturn(null);
        $container->shouldReceive('get')->with(CacheManager::class)->andReturn(new CacheManager($config, $logger));
        $container->shouldReceive('make')->with(FileSystemDriver::class, Mockery::any())->andReturnUsing(function ($class, $args) use ($container) {
            return new FileSystemDriver($container, $args['config']);
        });
        $container->shouldReceive('get')->with(PhpSerializerPacker::class)->andReturn(new PhpSerializerPacker());
        $cache = $container->get(CacheManager::class)->getDriver();
        echo get_class($cache);
        die();
        $cache = Mockery::mock(FileSystemDriver::class);
        $container->shouldReceive('get')->with(CacheInterface::class)->andReturn($cache);
        //$container->shouldReceive('get')->with(CacheInterface::class)->andReturn(new FileSystemDriver($container,$config->get('cache.default')));
//        $container->shouldReceive('get')->with(CacheInterface::class)->andReturnUsing(function ($class, $args) use ($container) {
//            return $container->get(CacheManager::class)->getDriver();
//        });
        echo get_class($container->get(CacheInterface::class));
        die();
        ApplicationContext::setContainer($container);
        return $container;
    }

    /**
     * @return array
     */
    protected function getPackageProviders()
    {
        return [
            PermissionServiceProvider::class,
        ];
    }

    /**
     * Set up the environment.
     */
    protected function getEnvironmentSetUp()
    {
        $config = require __DIR__ . '/../publish/permission.php';
        $this->config->set('permission', $config);
        $this->config->set('permission.cache.prefix', 'spatie_tests---');
    }

    /**
     * Set up the database.
     */
    protected function setUpDatabase()
    {
        $tableNames = config('permission.table_names');
        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
        Schema::dropIfExists('user');

        $this->config->set('permission.column_names.model_morph_key', 'model_test_id');

        Schema::create('user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email');
        });
        include_once __DIR__ . '/../database/migrations/create_permission_tables.php.stub';

        (new \CreatePermissionTables())->up();

        User::create(['email' => 'test@user.com']);
        $this->container->get(Role::class)->create(['name' => 'testRole']);
        $this->container->get(Role::class)->create(['name' => 'testRole2']);
        $this->container->get(Permission::class)->create(['name' => 'edit-articles']);
        $this->container->get(Permission::class)->create(['name' => 'edit-news']);
        $this->container->get(Permission::class)->create(['name' => 'edit-blog']);
        $this->container->get(Permission::class)->create(['name' => 'Edit News']);
    }

    /**
     * Reload the permissions.
     */
    protected function reloadPermissions()
    {
        $this->container->get(PermissionRegistrar::class)->forgetCachedPermissions();
    }

}
