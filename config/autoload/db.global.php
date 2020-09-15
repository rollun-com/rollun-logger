<?php

use Zend\Db\Adapter\AdapterInterface;

return [
    'dependencies' => [
        'aliases' => [
            'db' => AdapterInterface::class,
        ],
    ],
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'Pdo_Mysql',
        'database' => getenv('DB_NAME'),
        'username' => getenv('DB_USER'),
        'password' => getenv('DB_PASS'),
        'hostname' => getenv('DB_HOST'),
        'port' => getenv('DB_PORT') ?: 3306,
        'adapters' => [
            'sms_db' => [
                'driver' => getenv('SMS_DB_DRIVER'),
                'port' => getenv('SMS_DB_PORT'),
                'host' => getenv('SMS_DB_HOST'),
                'database' => getenv('SMS_DB_DATABASE'),
                'username' => getenv('SMS_DB_USERNAME'),
                'password' => getenv('SMS_DB_PASSWORD'),
            ],
        ],
    ],
];
