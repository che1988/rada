<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用入口文件

// 检测PHP环境
if(version_compare(PHP_VERSION,'5.3.0','<'))  die('require PHP > 5.3.0 !');

define('THXG_CLI_PATH', dirname(__FILE__)); 

// 运行环境(develop, produce, sandbox)
define('APP_STATUS', 'develop');

// 开启调试模式 建议开发阶段开启 部署阶段注释或者设为false
define('APP_DEBUG', true);

define('MODE_NAME', 'cli');

define('BUILD_DIR_SECURE', false);

define('RUNTIME_PATH', THXG_CLI_PATH.'/Runtime/');

// 定义应用目录
define('APP_PATH', THXG_CLI_PATH.'/Apps/');

// 引入ThinkPHP入口文件
require THXG_CLI_PATH.'/ThinkPHP/ThinkPHP.php';

// 亲^_^ 后面不需要任何代码了 就是如此简单
