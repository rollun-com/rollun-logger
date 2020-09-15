<?php

use Symfony\Component\Dotenv\Dotenv;
use Zend\ConfigAggregator\ArrayProvider;
use Zend\ConfigAggregator\ConfigAggregator;
use Zend\ConfigAggregator\PhpFileProvider;

// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean in
// `config/autoload/local.php`.
$cacheConfig = [
    'config_cache_path' => 'data/config-cache.php',
];

// Make environment variables stored in .env accessible via getenv(), $_ENV or $_SERVER.
(new Dotenv())->load('.env');

// Determine application environment ('dev' or 'prod').
$appEnv = getenv('APP_ENV');

$aggregator = new ConfigAggregator([
    \Zend\Validator\ConfigProvider::class,
    \Zend\Log\ConfigProvider::class,
    \rollun\tracer\ConfigProvider::class,
    \rollun\logger\ConfigProvider::class,
    \Zend\Db\ConfigProvider::class,
    \Zend\Cache\ConfigProvider::class,

    // Include cache configuration
    new ArrayProvider($cacheConfig),

    // Default App module config
    // Load application config in a pre-defined order in such a way that local settings
    // overwrite global settings. (Loaded as first to last):
    //   - `global.php`
    //   - `*.global.php`
    //   - `local.php`
    //   - `*.local.php`
    new PhpFileProvider('config/autoload/{{,*.}global,{,*.}local}.php'),

    // Load application config according to environment:
    //   - `global.dev.php`,   `global.test.php`,   `prod.global.prod.php`
    //   - `*.global.dev.php`, `*.global.test.php`, `*.prod.global.prod.php`
    //   - `local.dev.php`,    `local.test.php`,     `prod.local.prod.php`
    //   - `*.local.dev.php`,  `*.local.test.php`,  `*.prod.local.prod.php`
    new PhpFileProvider(realpath(__DIR__) . "/autoload/{{,*.}global.{$appEnv},{,*.}local.{$appEnv}}.php"),
], $cacheConfig['config_cache_path']);

return $aggregator->getMergedConfig();
