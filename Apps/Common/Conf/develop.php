<?php
return array(
	//********** Mysql数据库配置 **********
    // 数据库参数
    'DB_TYPE' => 'mysql',
    'DB_USER' => 'root',
    'DB_PWD' => 'password',
    'DB_DSN' => 'mysql:host=192.168.231.129;dbname=db_rada;charset=utf8',
    'DB_PREFIX' => 'tb_',
    'DB_FIELDS_CACHE' => false, 
    
    // 用户相关缓存参数
    'USER_CACHE' => array(
        'DATA_CACHE_TYPE' => 'Redis',
        'REDIS_HOST' => '192.168.1.124',
        'REDIS_PORT' => 6379,
        'REDIS_DB' => 0
    ),
);
