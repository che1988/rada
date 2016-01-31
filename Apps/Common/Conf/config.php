<?php
return array(
        'URL_MODEL' => 2,
        'ACTION_SUFFIX' => 'Action',

        // 日志设置
        'LOG_RECORD' => false,
        'LOG_LEVEL' => 'EMERG,ALERT,CRIT,ERR,WARN,NOTIC',
        'LOG_FILE_SIZE' =>  209715200,

        'LOAD_EXT_FILE' => 'const',

        // 注册命名空间
        'AUTOLOAD_NAMESPACE' => array (
                'Stock' => dirname ( THINK_PATH ) . '/Rada'
        )
);
