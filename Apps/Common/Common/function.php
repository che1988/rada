<?php
use Rada\RadaTools\Redis;
/**
 * 返回redis到句柄
 * @param string $host
 * @param string $port
 * @param string $db
 * @return Redis
 */
function getRedis($db=0, $host='', $port='', $auth=NULL) {
        static $_redis = array();
        $key = $host . "_" . $port . "_" . $db;
        if( isset($_redis[$key]) ) {
                return $_redis[$key];
        }

        empty($host) && $host = C('REDIS_HOST');
        empty($port) && $port = C('REDIS_PORT');
        //$db != 0     && $db   = C('REDIS_DB');
        $redis = new Redis();
        $redis->connect($host, $port);

        if (!empty($auth))
                $redis->auth($auth);

        $db != 0  && $redis->select($db);

        $_redis[$key] = $redis;
        return $_redis[$key];
} 

/**
 * 返回redis到句柄
 * @param string $config 参数
 * @return Redis
 */
function getRedisEx($config='') {
        if (empty($config))
                return getRedis();

        $redis_config = C($config);
        if (empty($redis_config)) {
                $redis_config = C('MIXED_CACHE');
                if (empty($redis_config))
                        return getRedis();
        }

        $redis_auth = isset($redis_config['REDIS_AUTH']) ? $redis_config['REDIS_AUTH'] : NULL;

        return getRedis($redis_config['REDIS_DB'], $redis_config['REDIS_HOST'], $redis_config['REDIS_PORT'], $redis_auth);
} 

/**
 * 生成随机密码
 * @param int $length 密码长度
 * @param string $type 密码类型
 * @return string
 */
function makeRandomString($length = 8, $type = 'all') {
    if (strcasecmp($type, 'number') == 0) {
        $seeds = '0123456789';
    } else if (strcasecmp($type, 'char') == 0) {
        $seeds = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    } else {
        $seeds = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    }
    $seed_len = strlen($seeds);

    $password = '';
    for ( $i = 0; $i < $length; $i++ ) {
        $password .= $seeds[mt_rand(0, $seed_len - 1)];
    }

    return $password;
}

/**
 * 加接口锁
 * @param string $key 
 * @param integer $wait_time 
 * @param strint $val
 * @param integer $lock_time
 */
function lock_action($key, $wait_time=0, $val='', $lock_time=60) {
        // 构造锁名称，锁内容
        $controller_name = strtolower(CONTROLLER_NAME);
        $action_name = strtolower(ACTION_NAME);
        $lock_key = 'system:action_lock:' . $key;
        $now_time = time();

        if (empty($val))
                $val = $now_time;

        $redis = getRedisEx('MIXED_CACHE');

        // 加锁
        $ret = $redis->setnx($lock_key, $val);
        if ($ret === false) {
                // 检测是否为死锁
                if ($redis->ttl($lock_key) === -1) {
                        $redis->expire($lock_key, $lock_time);
                }

                // 在等待时间内尝试加锁
                $curr_time = $now_time;
                $stop_time = $now_time + $wait_time;
                while ($curr_time < $stop_time) {
                        $_i = 0;
                        while (($ret === false) && ($_i < 10)) {
                                usleep(10000);
                                $_i ++;
                                $ret = $redis->setnx($lock_key, $val);
                        }
                        if ($ret === true)
                                break;

                        $curr_time = time();
                }

                // 加锁失败
                if ($ret === false)
                        return false;
        }

        // 加锁成功，设置过期时间，防止死锁
        $redis->expire($lock_key, $lock_time);
        return true;
}

/**
 * 删接口锁
 * @param string $key   锁名字
 */
function unlock_action($key) {
        $controller_name = strtolower(CONTROLLER_NAME);
        $action_name = strtolower(ACTION_NAME);
        $lock_key = 'system:action_lock:' . $key;

        $redis = getRedisEx('MIXED_CACHE');
        $redis->delete($lock_key);
}

/**
 * 发送短信
 * @param unknown $mobile
 * @param unknown $content
 */
function sendSms($mobile, $content) {
    
}
