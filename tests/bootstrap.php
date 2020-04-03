<?php

declare(strict_types = 1);

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
!defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);

Swoole\Runtime::enableCoroutine(true);

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';
