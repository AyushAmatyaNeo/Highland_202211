<?php

return [
    'db' => [
        'driver' => 'oci8',
        'connection_string' => '(DESCRIPTION =
        (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1521))
        (CONNECT_DATA =
        (SERVER = DEDICATED)
        (SERVICE_NAME = XE)
        )
        )',
        'username' => 'HLG7980',
        'password' => 'HLG7980',
        'platform_options' => ['quote_identifiers' => false]
    ],
    'service_manager' => [
        'factories' => [
            'Zend\Db\Adapter\Adapter' => 'Zend\Db\Adapter\AdapterServiceFactory',
        ],
    ],
];

//for postgres sql
//return [
//    'db' => [
//        'driver' => 'Pgsql',
//        'host'     => '10.255.0.10',
//        'username' => 'postgres',
//        'password' => 'test@2456',
//        'dbname' => 'postgres',
//        'port' => '5432'
//    ]
//];

