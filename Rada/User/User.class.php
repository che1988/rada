<?php
namespace Rada\User;

use Rada\Exception\RadaException;
/**
 * user类
 * @author 
 *
 */
class User extends UserConst {
    
    /**
     * 验证手机号
     * @param unknown $user_id
     */
    public function verifyMobile($mobile, $code) {
        $redis = getRedisEx('USER_CACHE');
        $info = $redis->get(self::USER_REGISTER_SMS_PRIFIX.$mobile);
        if (empty($info))
            throw new RadaException('验证码已过期或未发送');
        
        $info = json_decode($info, true);
        if ($info['ctime'] - NOW_TIME > 600)
            throw new RadaException('对不起，您的验证码已经过期，请重新发送');
        
        if ($info['code'] != $code)
            throw new RadaException('对不起，您的验证码有误');
        
        return true;
    }
    
    /**
     * 发送注册验证短信
     * @param unknown $mobile
     */
    public function sendVirifySms($mobile) {
        // 验证该手机号是否已经被注册
        $where = array('mobile' => $mobile);
        $user_info = D('User')->where($where)->find();
        if ($user_info)
            throw new RadaException('该手机号已经被注册');
        
        $code = makeRandomString(6, 'number');
        $content = sprintf('你的注册验证码是$d', $code);
        $res = sendSms($mobile, $content);
        if (!$res)
            throw new RadaException('发送短信失败，请重试');
        
        // redis记录验证码
        $redis = getRedisEx('USER_CACHE');
        $data = array(
            'mobile' => $mobile,
            'code'   => $code,
            'ctime'  => NOW_TIME
        );
        $redis->setex(self::USER_REGISTER_SMS_PRIFIX.$mobile, 600, json_encode($data));
        return true;
    }
    
    /**
     * 注册入库
     * @param unknown $email 邮箱
     * @param unknown $password 密码
     * @param unknown $recommend 推荐人
     * @param unknown $recommend_leader 推荐人领导
     * @param unknown $area 区域（A,B,C）
     * @param unknown $safe_password 安全密码
     * @param unknown $mobile 手机号
     */
    public function register($email, $password, $recommend, $recommend_leaderid, $area, $safe_password, $mobile) {
        $data = array(
            'email'                     => $email,
            'password'                  => $password,
            'recommend_userid'          => $recommend,
            'recommend_leaderid'        => $recommend_leaderid,
            'area'                      => $area,
            'safe_password'             => $safe_password,
            'mobile'                    => $mobile,
            'ctime'                     => date('Y-m-d H:i:s', NOW_TIME),
            'utime'                     => date('Y-m-d H:i:s', NOW_TIME),
            'c_ip'                      => get_client_ip(1),
            'status'                    => 1
        );
        $id = D('User')->add($data);
        if (!$id)
            throw new RadaException('系统错误，注册失败');
        
        return true;
    }
    
    /**
     * 获取用户信息，优先使用缓存
     * @param unknown $user_id
     */
    public static function getUserInfo($user_id) {
        $redis = getRedisEx('USER_CACHE');
        $info = $redis->hGetAll(self::USER_INFO_PREFIX . $user_id);
        if (empty($info)) {
            $info = D('User')->find($user_id);
            if (empty($info))
                throw new RadaException('没有该用户');
            // 同步到redis
            $redis->hMset(self::USER_INFO_PREFIX.$user_id, $info);
            $redis->expire(self::USER_INFO_PREFIX.$user_id, 864000);
        }
        return $info;
    }
}