<?php
return array(
	//********** Mysql数据库配置 **********
    // 数据库参数
    'DB_TYPE' => 'mysql',
    'DB_USER' => 'root',
    'DB_PWD' => 'password',
    'DB_DSN' => 'mysql:host=127.0.0.1;dbname=rada;charset=utf8',
    'DB_PREFIX' => 'tb_',
    'DB_FIELDS_CACHE' => false, 
    
    // 缓存参数
    'MIXED_CACHE' => array(
        'DATA_CACHE_TYPE' => 'Redis',
        'REDIS_HOST' => '127.0.0.1',
        'REDIS_PORT' => 6379,
        'REDIS_DB' => 0
    ),
);
