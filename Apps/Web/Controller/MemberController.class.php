<?php

namespace Api\Controller;

use Think\Controller;
use Think\Log;
use Org\Net\Http;
use Stock\Collect\Collect;
use Stock\Exception\BullException;

class MemberController extends Controller {
	const cache_config = 'USER_CACHE';
	const UPLOAD_PATH = '/user_avatar/';
	const UPLOAD_PARAM_URL = 1; //URL上传参数
	const UPLOAD_PARAM_DATA = 2; //数据上传参数

	/**
	 * 更新设备与用户状态
	 * @param int $device_id 设备ID
	 * @param int $user_id 用户ID
	 * @return 
	 */
	private function upload_status($device_id, $user_id) {
		$machine = I('post.machine', '');
		$os = I('post.os', '');
		$version = I('version', '');
		$client_id = I('post.clientid', '');
		$client_ip = get_client_ip(1);

		$now_time = date('Y-m-d H:i:s', NOW_TIME);

		$redis = getRedisEx(self::cache_config);

		// 更新设备状态
		if (!empty($device_id)) {
			$device['id'] = $device_id;
			$device['utime'] = $now_time;
			$device['uip'] = $client_ip;
			if (!empty($machine))
				$device['machine'] = $machine;
			if (!empty($os))
				$device['os_version'] = $os;
			if (!empty($version))
				$device['app_version'] = $version;
			if (!empty($client_id))
				$device['client_id'] = $client_id;

			$index = $device_id % 10;
			$redis->hSet(DEVICE_NEWEST_STATUS_PREFIX.$index, $device_id, json_encode($device));
		}

		// 更新用户状态
		if (!empty($user_id)) {
			$user['id'] = $user_id;
			$user['utime'] = $now_time;
			if (!empty($device_id))
				$user['udevice_id'] = $device_id;
			$user['uip'] = $client_ip;

			$index = $user_id % 10;
			$redis->hSet(MEMBER_NEWEST_STATUS_PREFIX.$index, $user_id, json_encode($user));
		}
	}
	
	/**
	 * 
	 */

	// 检测自推广系统
	private function _checkInPpp($mobile, $password) {
		$post = array('mobile' => $mobile);
		$post['passwd'] = $password;
		$ppp_ret = \Org\Net\Http::fsockopenDownload(C('PPP_SYSTEM_URL').'/ppp/inviteAction/IsExistUser', array('post' => $post));
		if (empty($ppp_ret)) {
			\Think\Log::write('访问失败PPP服务器失败:' . $mobile);
			return false;
		}
	
		$ppp_info = json_decode($ppp_ret, true);
		if (empty($ppp_info)) {
			\Think\Log::write('解析PPP回执错误:' . $ppp_ret);
			return false;
		}
	
		if ($ppp_info['data']['invitedUser'] === true) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * 随机生成一个系统的随机头像（默认头像共有86个，0-85.png）
	 */
	private function _getRandomAvatar() {
		$avatar_id = rand(0, 85);
		//$url = C('UPLOAD_UPYUN_CONFIG');
		$avatar_url = '/user_avatar/default/' . $avatar_id . '.png';
		return $avatar_url;
	}

	/**
	 * 上传又拍云
	 * @param mixed $source 图像源
	 * @param int $type 参数类型  1.url 2.数据
	 * @param string $desc 目标文件名
	 * @return string 
	 */
	public function uploadUpyun($source, $type, $dest) {
		// 获取图像数据
		if ($type == self::UPLOAD_PARAM_URL) {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $source);
			curl_setopt($curl, CURLOPT_TIMEOUT, 5);
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$image_data = curl_exec($curl);
			curl_close($curl);
			if (empty($image_data))
				return null;

			$ext = pathinfo($source, PATHINFO_EXTENSION);
		} elseif ($type == self::UPLOAD_PARAM_DATA) {
			$image_data = $source;
			if (empty($image_data))
				return null;
		} else {
			return null;
		}

		// 判断文件后缀名
		if (empty($ext)) {
			$file = substr($image_data, 0, 2);
			$image_head = @unpack("C2chars", $file);
			$type_code = intval($image_head['chars1'].$image_head['chars2']);
			switch ($type_code) {
				case 255216: $ext = 'jpg'; break;
				case 7173: $ext = 'gif'; break;
				case 6677: $ext = 'bmp'; break;
				case 13780: $ext = 'png'; break;
				default: $ext = '';
			}
		}

		// 获取上传路径
		if (empty($dest) || substr($dest, -1) != '/')
			$dest .= '/';

		// 上传又拍云
		$file_name = $dest . NOW_TIME . '.' . $ext;
		$options = C('UPLOAD_UPYUN_CONFIG');
		$upyun = new \Org\Net\UpYun($options['bucket'], $options['username'], $options['password']);
		try {	
			$rsp = $upyun->writeFile($file_name, $image_data, True);
			if ($rsp)
				return $file_name;
			else
				return null;
		} catch (\Exception $e) {
			Log::record('上传图片错误, 错误码:' . $e->getCode() . ', 错误信息:'. $e->getMessage());
			return null;
		}
	}

	/**
	 * 创建Token
	 * @access private
	 * @param $uid 用户id
	 * @param $ctime 用户创建时间
	 * @param $expired 用户有效期
	 * @return string token串
	 */
	private function makeToken($uid, $ctime, $key, $expired) {
		return sha1($uid . $ctime . $key . $expired);
	}

	/**
	 * 合并用户的收藏股票
	 * @access private
	 * @param $oldid 原用户id
	 * @param $newid 新用户id
	 * @return void
	 */
	private function mergeCollect($oldid, $newid) {
		$tablelist = array(
				array('name' => 'UserViewpointCollect', 'prefix' => MEMBER_COLLECT_VIEWPOINT_PREFIX),
				array('name' => 'UserAnalystCollect', 'prefix' => MEMBER_COLLECT_ANALYST_PREFIX)
		);

		foreach($tablelist as $table) {
			$collectModel = D($table['name']);
			$rows = $collectModel->where("user_id in ('$oldid','$newid')")->select();

			$oldlist = array();
			$newlist = array();
			if (!empty($rows)) {
				foreach($rows as $line) {
					$line = $collectModel->parseFieldsMap($line);
					if ($line['user_id'] == $oldid) {
						array_push($oldlist, $line['collect_id']);
					} else {
						array_push($newlist, $line['collect_id']);
					}
				}
			}

			if (count($oldlist) == 0) {
				;
			} else if (count($newlist) == 0) {
				$data['user_id'] = $newid;
				$collectModel->where('user_id='.$oldid)->field('user_id')->save($data);
			} else {
				$difflist = array_diff($oldlist, $newlist);
				if (count($difflist) > 0) {
					$where['user_id'] = $oldid;
					$where['collect_id'] = array('IN', $difflist);
					$where = $collectModel->parseFieldsMap($where, 0);
					$data['user_id'] = $newid;
					$collectModel->where($where)->field('user_id')->save($data);
				}
				$collectModel->where('user_id='.$oldid)->delete();
			}
			
			$redis = getRedisEx('USER_CACHE');
			$redis->del($table['prefix'].$newid);
			$redis->del($table['prefix'].$oldid);
		}
		$this->margeFansCollect($oldid, $newid);
		$this->margeStock($oldid, $newid);
		$this->margeWeibo($oldid, $newid);	
	}
	
	/**
	 * 合并 关注
	 */
	private function margeFansCollect($oldid, $newid){
		$collect_sdk = new Collect();
		$collect_list = $collect_sdk -> select_collect($oldid);
		if(!empty($collect_list)){
			foreach($collect_list as $k=>$_id){
				try{			
					$collect_sdk -> _cacheFansCollect($oldid, 'del', substr($_id, 3), 'guest');
					$collect_sdk -> addMemberCollect($newid, substr($_id, 3));				
				}catch (BullException $e){
					
				}
			}
		}	
	}
	
	/**
	 * 合并 自选股
	 */
	private function margeStock($oldid, $newid){
		$collect_sdk = new Collect();
		$stock_list = $collect_sdk->select_stock_list($oldid);
		if(!empty($stock_list)){
			foreach($stock_list as $_code=>$v){
				try{
					$collect_sdk->_cacheStock($oldid, 'del', substr($_code, 3));
					$collect_sdk->_cacheStock($newid, 'add', substr($_code, 3));
				}catch (BullException $e){
				
				}
			}
		}
	}
	
	/**
	 * 合并微博
	 */
	private function margeWeibo($oldid, $newid){
		$collect_sdk = new Collect();
		$weibo_total = $collect_sdk->totalWeiboCollect($oldid);
		
		if($weibo_total > 0){
			try{
				$weibo_list = $collect_sdk->listWeiboCollect($oldid, 0, $weibo_total);
				foreach ($weibo_list as $_weiboid){
					$collect_sdk -> delWeiboCollect($oldid, $_weiboid);
					$collect_sdk -> addWeiboCollect($newid, $_weiboid);
				}
			}catch (BullException $e){
			
			}
		}
	}

	/**
	 * 用户注册接口
	 * @access public
	 * @return void
	 */
	public function registerAction() {
		$gid = I('post.gid');
		$_expired = I('post.expired', '');
		$client_ip = get_client_ip(1);
		$now_time = NOW_TIME;

		// 创建设备函数
		$create_device = function ($gid) use ($client_ip, $now_time) {
			$machine = I('post.machine', '');
			$_platform = I('post.platform', '');
			$os = I('post.os', '');
			$qudao = I('post.qudao', '');
			$version = I('version', '');
			$client_id = I('post.clientid', '');

			// 参数校验
			if (empty($gid) || empty($_platform) || empty($qudao))
				return false;

			switch ($_platform) {
				case 1:
					$platform = 'ios';
					break;
				case 2:
					$platform = 'android';
					break;
				default:
					return false;
			}

			// 创建设备
			$deviceModel = D('Device');
			$device['status'] = DEVICE_STATUS_NORMAL;
			$device['guid'] = $gid;
			$device['ctime'] = date('Y-m-d H:i:s', $now_time);
			$device['cip'] = $client_ip;
			$device['ltime'] = $device['ctime'];
			$device['lip'] = $device['cip'];
			$device['utime'] = $device['ctime'];
			$device['uip'] = $device['cip'];
			$device['platform'] = $platform;
			$device['channel'] = $qudao;
			$device['guest_id'] = 0;
			if (!empty($machine))
				$device['machine'] = $machine;
			if (!empty($os))
				$device['os_version'] = $os;
			if (!empty($version))
				$device['app_version'] = $version;
			if (!empty($client_id))
				$device['client_id'] = $client_id;
			$device_id = $deviceModel->add($device);
			if (empty($device_id))
				return false;

			return (int)$device_id;
		};

		// 创建游客函数
		$create_guest = function ($device_id) use ($client_ip, $now_time) {
			$userModel = D('User');
			$user['login_type'] = MEMBER_TYPE_GUEST;
			$user['user_type'] = MEMBER_TYPE_GUEST;
			$user['user_level'] = 0;
			$user['status'] = MEMBER_STATUS_NORMAL;
			$user['name'] = '游客';
			$user['ctime'] = date('Y-m-d H:i:s', $now_time);
			$user['cdevice_id'] = $device_id;
			$user['cip'] = $client_ip;
			$user['ltime'] = $user['ctime'];
			$user['ldevice_id'] = $user['cdevice_id'];
			$user['lip'] = $user['cip'];
			$user['utime'] = $user['ctime'];
			$user['udevice_id'] = $user['cdevice_id'];
			$user['uip'] = $user['cip'];
			$user_id = $userModel->add($user);
			if (empty($user_id))
				return false;

			$deviceModel = D('Device');
			$device_where = array('id' => $device_id);
			$ret = $deviceModel->where($device_where)->setField('guest_id', $user_id);
			if (empty($ret)) {
				$user_where = array('id' => $user_id);
				$userModel->where($user_where)->delete();
				return false;
			}

			return $user_id;
		};

		//---------- 方法开始 ----------
		// 参数校验
		if (empty($gid))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$expired = empty($_expired) ? $now_time + C('DEFAULT_TOKEN_EXPIRED') : (int)$_expired;
		if ($expired < $now_time)
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		// 加锁保护
		if (lock_action($gid) == false)
			$this->ajaxReturn(responseError(RETURN_STATUS_REPEAT_ERROR, '请求过于频繁'));

		// 判断是否为新设备
		$deviceModel = D('Device');
		$device_where = array('guid' => $gid);
		$device = $deviceModel->field('id,status,guest_id')->where($device_where)->find();
		if (empty($device)) {
			// 创建设备
			$device_id = $create_device($gid);
			if (empty($device_id)) {
				Log::write('创建设备失败, gid:' . $gid);
				unlock_action($gid);
				$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '系统错误'));
			}

			$guest_id = 0;
		} else {
			if ($device['status'] == DEVICE_STATUS_FROZEN) {
				unlock_action($gid);
				$this->ajaxReturn(responseError(RETURN_STATUS_STATUS_ERROR, '设备已冻结'));
			}

			$device_id = (int)$device['id'];
			$guest_id = (int)$device['guest_id'];

			// 更新设备登录信息
			$device_where = array('id' => $device_id);
			$device_data = array('ltime' => date('Y-m-d H:i:s', $now_time));
			$device_data['lip'] = $client_ip;
			$device_data['utime'] = $device_data['ltime'];
			$device_data['uip'] = $device_data['lip'];
			$deviceModel->where($device_where)->save($device_data);
		}

		// 判断是否绑定虚拟用户
		if ($guest_id == 0) {
			// 创建游客
			$guest_id = $create_guest($device_id);
			if (empty($guest_id)) {
				Log::write('创建游客失败, device_id:' . $device_id);
				unlock_action($gid);
				$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '系统错误'));
			}
		} else {
			// 更新游客登录信息
			$guestModel = D('User');
			$guest_where = array('id' => $guest_id);
			$guest_data = array('ltime' => date('Y-m-d H:i:s', $now_time));
			$guest_data['lip'] = $client_ip;
			$guest_data['utime'] = $guest_data['ltime'];
			$guest_data['uip'] = $guest_data['lip'];
			$guestModel->where($guest_where)->save($guest_data);
		}

		// 清除该设备的历史Token
		$tokenModel = D('UserToken');
		$token_where = array('device_id' => $device_id);
		$token_data = array('isdel' => 1);
		$tokenModel->where($token_where)->save($token_data);

		// 创建Token
		$token['key'] = makeRandomString(16);
		$token['token'] = $this->makeToken($guest_id, $now_time, $token['key'], $expired);
		$token['user_id'] = $guest_id;
		$token['device_id'] = $device_id;
		$token['ctime'] = date('Y-m-d H:i:s', $now_time);
		$token['utime'] = $token['ctime'];
		$token['expired'] = date('Y-m-d H:i:s', $expired);
		$token_id = $tokenModel->add($token);
		if (empty($token_id)) {
			Log::write('创建Token失败, device_id:'.$device_id.',uid:'.$guest_id, Log::ERR);
			unlock_action($gid);
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '系统错误'));
		}

		// 进行模拟器过滤
		is_simulator(array('type' => 'register', 'device_id' => $device_id));

		// 返回消息包
		$guest = getMemberInfo($guest_id);
		$data['token'] = $token['token'];
		$data['expired'] = $expired;
		$data['uid'] = (int)$guest_id;
		$data['type'] = $guest['type'];
		$data['name'] = $guest['name'];
		$data['mobile'] = $guest['mobile'];
		$data['avatar'] = $guest['avatar'];

		unlock_action($gid);
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, $data));
	}

	/**
	 * 绑定第三方帐号接口
	 * @access public
	 * @return void
	 */
	public function bindAction() {
		$token = I('post.token', '');
		$type = I('post.type', '');
		$guid = I('post.guid', '');
		$password = I('post.password', '');
		$name = trim(I('post.name', ''));
		$avatar = I('post.avatar', '');
		$_platform = I('post.platform', '');
		$client_ip = get_client_ip(1);
		$now_time = NOW_TIME;

		// 创建用户函数
		$create_user = function ($device_id, $type, $guid, $name, $avatar) use ($client_ip, $now_time) {
			// 构造用户信息
			if ($type == MEMBER_TYPE_QQ) {
				$user['login_type'] = MEMBER_TYPE_QQ;
				$user['qq'] = $guid;
			} elseif ($type == MEMBER_TYPE_WEIBO) {
				$user['login_type'] = MEMBER_TYPE_WEIBO;
				$user['weibo'] = $guid;
			} elseif ($type == MEMBER_TYPE_WEIXIN) {
				$user['login_type'] = MEMBER_TYPE_WEIXIN;
				$user['weixin'] = $guid;
			}
			$user['user_type']   = 'user';
			$user['user_level'] = 0;
			$user['status']      = MEMBER_STATUS_NORMAL;
			$user['name'] 			= $name;
			$user['avatar'] 		= $this->_getRandomAvatar();
			$user['ctime'] 		= date('Y-m-d H:i:s', $now_time);
			$user['cdevice_id'] 	= $device_id;
			$user['cip'] 			= $client_ip;
			$user['ltime'] 		= $user['ctime'];
			$user['ldevice_id'] 	= $user['cdevice_id'];
			$user['lip'] 			= $user['cip'];
			$user['utime'] 		= $user['ctime'];
			$user['udevice_id'] 	= $user['cdevice_id'];
			$user['uip'] 			= $user['cip'];

			$userModel = D('User');
			$user_id = $userModel->add($user);
			if (empty($user_id)) {
				return false;
			}

			// 上传头像
			if (!empty($avatar)) {
				$avatar_file = $this->uploadUpyun($avatar, self::UPLOAD_PARAM_URL, self::UPLOAD_PATH.$user_id);
				if (!empty($avatar_file)) {
					$user['id'] = $user_id;
					$user['avatar'] = $avatar_file;
					$userModel->save($user);
				}
			}

			return (int)$user_id;
		};

		// 参数检查
		if (empty($token) || empty($type) || empty($guid))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		if ($type != MEMBER_TYPE_QQ && $type != MEMBER_TYPE_WEIBO && $type != MEMBER_TYPE_MOBILE && $type != MEMBER_TYPE_WEIXIN)
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '用户类型错误'));

		if ($type == MEMBER_TYPE_MOBILE && empty($password))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '请输入密码'));

		if ($type != MEMBER_TYPE_MOBILE) {
			if(empty($name))
				$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));
		}

		$guest = verifyToken($token, true);
		if (empty($guest))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));

		if ($guest['type'] != MEMBER_TYPE_GUEST) {
			// $this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '必须为游客'));
			// 为了解决客户端，切换成游客
			Log::write('用户状态错误, 用户ID:' . $guest['id']);

			$guest_id = D('Device')->where(array('id' => $guest['device_id']))->getField('guest_id');
			if (empty($guest_id)) {
				Log::write('设备无对应游客ID, 设备ID:'. $guest['device_id']);
				$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '获取设备绑定用户失败'));
			}

			$guest['id'] = $guest_id;			
		}

		// 加锁保护
		if (lock_action($token) == false)
			$this->ajaxReturn(responseError(RETURN_STATUS_REPEAT_ERROR, '请求过于频繁'));

		// 根据绑定用户类型，判断是否存在
		$userModel = D('User');
		if ($type == MEMBER_TYPE_QQ) {
			$where['login_type'] = MEMBER_TYPE_QQ;
			$where['qq'] = $guid;
		} elseif ($type == MEMBER_TYPE_WEIBO) {
			$where['login_type'] = MEMBER_TYPE_WEIBO;
			$where['weibo'] = $guid;
		} elseif ($type == MEMBER_TYPE_WEIXIN) {
			$where['login_type'] = MEMBER_TYPE_WEIXIN;
			$where['weixin'] = $guid;
		} elseif ($type == MEMBER_TYPE_MOBILE) {
			$where['login_type'] = MEMBER_TYPE_MOBILE;
			$where['mobile'] = $guid;
			$where['password'] = substr(sha1($password),0,32);
		}
		$user = $userModel->field('id,status')->where($where)->find();
		if (empty($user)) {
			if ($type == MEMBER_TYPE_MOBILE) {
				//判断用户输入的密码是否符合子推广的格式
				if (strlen($password) == 8 && substr($password, 0, 2) == 'gn') {
					if ($this->_checkInPpp($guid, $password)) {
						unlock_action($token);
						$this->ajaxReturn(responseError(RETURN_STATUS_PPP, '请先升级至最新版本'));
					}
				}

				unlock_action($token);
				$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '手机号或密码错误'));
			} else {
				// 过滤包含公牛的昵称
				$gong_pos = strpos($name, '公');
				$niu_pos = strrpos($name, '牛');
				if ($gong_pos !== false && $niu_pos !== false) {
					if ($niu_pos > $gong_pos) {
						unlock_action($token);
						$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '用户名已存在'));
					}
				}

				// 判断是否重名
				$user = $userModel->where(array('name' => $name))->find();
				if (!empty($user)) {
					unlock_action($token);
					$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '用户名已存在'));
				}

				// 添加新用户
				$user_id = $create_user($guest['device_id'], $type, $guid, $name, $avatar);
				if (empty($user_id)) {
					Log::write('创建用户失败, device_id:' . $device_id);
					unlock_action($token);
					$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '系统错误'));
				}
			}
		} else {
			if ($user['status'] == MEMBER_STATUS_FROZEN) {
				unlock_action($token);
				$this->ajaxReturn(responseError(RETURN_STATUS_STATUS_ERROR, '该账号已被冻结'));
			}

			$user_id = (int)$user['id'];

			// 更新用户表
			$user_where = array('id' => $user_id);
			$user_data = array('ltime' => date('Y-m-d H:i:s', $now_time));
			$user_data['ldevice_id'] = $guest['device_id'];
			$user_data['lip'] = $client_ip;
			$user_data['utime'] = $user_data['ltime'];
			$user_data['udevice_id'] = $user_data['ldevice_id'];
			$user_data['uip'] = $user_data['lip'];
			$userModel->where($user_where)->save($user_data);

/* 允许用户同时登录多个设备，所以暂时还不清除该用户其他Token
			// 如果存在，就清除其他Token
			$token_where = array('user_id' => $user_id);
			$tokens = $tokenModel->where($token_where)->getField('token', true);
			if (!empty($tokens)) {
				foreach ($tokens as $v)
					$redis->del(MEMBER_TOKEN_PREFIX . $v);
				$tokenModel->where($token_where)->delete();
			}
*/
		}

		//创建账户
 		$request = (int)$user_id;
		$status = Http::fsockopenDownload(C('MEMBER_REGISTER_ACCOUNT_URL'), array('post' => $request));
		if (empty($status)) {
			\Think\Log::write("新用户创建账户失败;uid: $user_id.", \Think\Log::ERR);
		} 
		
		$json = json_decode($status);
		if ($json->resultcode != '0') {
			\Think\Log::write("新用户创建系统失败，错误提示: : $json->resultmsg.", \Think\Log::ERR);
		}

		// 合并收藏
		$this->mergeCollect($guest['id'], $user_id);

		// 更新
		$tokenModel = D('UserToken');
		$token_data = array('token' => $token);
		$token_data['user_id'] = $user_id;
		$token_data['utime'] = date('Y-m-d H:i:s', $now_time);
		$tokenModel->save($token_data);

		// 更新设备状态
		$this->upload_status($guest['device_id'], $user_id);

		// 清除缓存
		$userRedis = getRedisEx(self::cache_config);
		$userRedis->del(MEMBER_TOKEN_PREFIX . $token);
		$userRedis->del(MEMBER_INFO_PREFIX . $guest['id']);
		$userRedis->del(MEMBER_INFO_PREFIX . $user_id);

		// 进行模拟器过滤
		is_simulator(array('type' => 'bind', 'device_id' => $guest['device_id'], 'user_id' => $user_id));

		/**
		 * 名称:微信账户打通
		 * 功能:收集微信UnionID 
		 * 日期:2015-10-27
		 */
		if ($type == MEMBER_TYPE_WEIXIN) {
			$unionid = I ( 'post.unionid' );
			if ($unionid) {
				$WeChatModel = D ( 'UnifiedUserUnionid' );
				$udata ['user_id'] = ( int ) $user_id;
				$udata ['openid']  = $guid;
				$udata ['unionid'] = $unionid;
				$udata ['ctime']   = date ( 'Y-m-d H:i:s', time () );
				$WeChatModel->add ( $udata );
			}
		}

		// 返回消息包
		$user = getMemberInfo($user_id);
		$data['uid'] = (int)$user_id;
		$data['type'] = $user['type'];
		$data['name'] = $user['name'];
		$data['mobile'] = $user['mobile'];
		$data['avatar'] = $user['avatar'];
		$data['id'] = $data['uid'];

		unlock_action($token);
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, $data));
	}

	/**
	 * 快速绑定接口
	 */
	public function easybindAction() {
		$_expired = I('post.expired', '');
		$gid = I('post.gid', '');
		$type = I('post.type', '');
		$guid = I('post.guid', '');
		$password = I('post.password', '');

		// 参数检查
		if (empty($gid) || empty($type) || empty($guid))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		if ($type != MEMBER_TYPE_MOBILE && $type != MEMBER_TYPE_QQ && $type != MEMBER_TYPE_WEIBO)
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '用户类型错误'));

		if ($type == MEMBER_TYPE_MOBILE && empty($password))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '手机用户，口令不能为空'));

		$deviceModel = D('Device');
		$device_where = array('guid' => $gid);
		$device_id = $deviceModel->where($device_where)->getField('id');
		if (empty($device_id))
			$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '非注册设备'));

		$userModel = D('User');
		if ($type == MEMBER_TYPE_MOBILE) {
			$user_where = array('login_type' => MEMBER_TYPE_MOBILE);
			$user_where['mobile'] = $guid;
			$user_where['password'] = substr(sha1($password),0,32);
		} elseif ($type == MEMBER_TYPE_WEIBO) {
			$user_where = array('login_type' => MEMBER_TYPE_WEIBO);
			$user_where['weibo'] = $guid;
		} elseif ($type == MEMBER_TYPE_QQ) {
			$user_where = array('login_type' => MEMBER_TYPE_QQ);
			$user_where['qq'] = $guid;
		}
		$user_id = $userModel->where($user_where)->getField('id');
		if (empty($user_id))
			$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '非注册用户'));

		// 删除相关所有Token
		$tokenModel = D('UserToken');
		$token_where = array('device_id' => $device_id);
/* 为兼容老版本客户端，暂时注释掉
		$token_where['user_id'] = $user_id;
		$token_where['_logic'] = 'OR';
*/
		$tokenModel->where($token_where)->delete();

		// 创建Token
		$now_time = NOW_TIME;
		$expired = empty($_expired) ? $now_time + C('DEFAULT_TOKEN_EXPIRED') : (int)$_expired;
		if ($expired < $now_time)
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$token['key'] = makeRandomString(16);
		$token['token'] = $this->makeToken($user_id, $now_time, $token['key'], $expired);
		$token['user_id'] = $user_id;
		$token['device_id'] = $device_id;
		$token['ctime'] = date('Y-m-d H:i:s', $now_time);
		$token['utime'] = $token['ctime'];
		$token['expired'] = date('Y-m-d H:i:s', $expired);
		$token_id = $tokenModel->add($token);
		if (empty($token_id)) {
			\Think\Log::write('创建Token失败, device_id:'.$device_id.',uid:'.$user_id, \Think\Log::ERR);
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '系统错误'));
		}

		// TODO 更新设备和用户状态
		//

		// 返回消息包
		$user = getMemberInfo($user_id);
		$data['token'] = $token['token'];
		$data['expired'] = $expired;
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, $data));
	}

	/**
	 * 解绑第三方接口
	 * @access public
	 * @return void
	 */
	public function unbindAction() {
		$token = I('post.token');

		// 参数检查
		if (empty($token))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$user = verifyToken($token, true);
		if (empty($user))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));

		if ($user['type'] == MEMBER_TYPE_GUEST)
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '用户类型错误'));

		if (empty($user['device_id'])) {
			\Think\Log::write('用户无对应的设备ID, 游客ID:'. $user['id']);
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '转换游客模式错误'));
		}

		// 取设备绑定游客ID 
		$guest_id = D('Device')->where(array('id' => $user['device_id']))->getField('guest_id');
		if (empty($guest_id)) {
			\Think\Log::write('设备无对应游客ID, 设备ID:'. $user['device_id']);
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '转换游客模式失败'));
		}

		// 更新Token绑定关系
		$tokenModel = D('UserToken');
		$token_data = array('token' => $token);
		$token_data['utime'] = date('Y-m-d H:i:s', NOW_TIME);
		$token_data['user_id'] = $guest_id;
		$tokenModel->save($token_data);

		// 清除缓存
		$redis = getRedisEx(self::cache_config);
		$redis->del(MEMBER_TOKEN_PREFIX . $token);

		// TODO 更新游客的登录状态
		//
		
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, json_decode('{}')));
	}
	
	/**
	 * 更新Token接口
	 */
	public function updatetokenAction() {
		$token = I('post.token', '');
		$_expired = I('post.expired', '');

		// 参数检查
		if (empty($token))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$user = verifyToken($token, true);
		if (empty($user))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '认证错误'));

		$now_time = NOW_TIME;
		$expired = empty($_expired) ? $now_time + C('DEFAULT_TOKEN_EXPIRED') : (int)$_expired;
		if ($expired < $now_time)
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		// 加锁
		if (lock_action($token) == false)
			$this->ajaxReturn(responseError(RETURN_STATUS_REPEAT_ERROR, '请求过于频繁'));

		// 删除Token
		$where['device_id'] = $user['device_id'];
		$tokenModel = D('UserToken');
		$tokenModel->where($where)->delete();

		// 创建Token
		$data['user_id'] = $user['id'];
		$data['device_id'] = $user['device_id'];
		$data['key'] = makeRandomString(16);
		$data['token'] = $this->makeToken($data['user_id'], NOW_TIME, $data['key'], $expired);
		$data['ctime'] = date('Y-m-d H:i:s', NOW_TIME);
		$data['expired'] = date('Y-m-d H:i:s', $expired);
		$tokenModel->add($data);

		// 更新设备和用户状态
		$this->upload_status($user['device_id'], $user['id']);

		// 返回结果
		$return = array('token' => $data['token']);
		$return['expired'] = $expired;

		unlock_action($token);
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, $return));
	}

	/**
	 * 我的信息接口
	 */
	public function myinfoAction() {
		$token = I('post.token', '');
		
		// 参数检查
		if (empty($token))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));
		
		$user = verifyToken($token, true);
		if (empty($user))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));

		//$status = \Org\Net\Http::fsockopenDownload(C('PPP_SYSTEM_URL').'ppp/invite/hasNewPrize?userid='.$user['id']);
		$member['hasNewPrize'] = false;
		// 返回结果
		$member['id'] = $user['id'];
		$member['ctime'] = $user['ctime'];
		$member['type'] = $user['type'];
		$member['name'] = $user['name'];
		$member['avatar'] = $user['avatar'];
		$member['mobile'] = $user['mobile'];
		$member['fans'] = $user['fans'];
		$member['collect'] = $user['collect'];
		$member['collect_stock'] = $user['collect_stock'];
		$member['description'] = $user['description'];
		$member['interest'] = $user['interest'];
		if ($user['type'] == MEMBER_TYPE_GUEST) {
			$member['avgWeekYieldRate'] = 0;
			$member['assets'] = 0;
		} else {
			$account = A('Account')->getAccount($user['id'], true);
			$member['avgWeekYieldRate'] = $account['avgWeekYieldRate'];
			$member['assets'] = $account['totalAsset'];
		}

		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, $member));
	}

	/**
	 * 生成验证码
	 */
	public function makecodeAction() {
		$token = I('post.token', '');
		$mobile =  I('post.mobile', '');
		$type = I('post.type', 'bind');

		// 参数检查
		if (empty($token) || empty($mobile))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$member = VerifyToken($token, true);
		if (empty($member))
			$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '用户不存在'));

		$map = array('mobile' => $mobile);
		$mobile_user = D('User')->field('id,login_type')->where($map)->find();

		// 绑定类型校验不同用户身份
		if ($type == 'register') {
			if ($member['type'] != MEMBER_TYPE_GUEST)
				$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '非游客用户'));

			if (!empty($mobile_user))
				$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '手机号已存在'));
		} elseif ($type == 'forget') {
			if ($member['type'] != MEMBER_TYPE_GUEST)
				$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '非游客用户'));

			if (empty($mobile_user))
				$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '手机号错误'));

			switch ($mobile_user['login_type']) {
				case MEMBER_TYPE_MOBILE:
					break;
				case MEMBER_TYPE_QQ:
					$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '您是QQ注册用户，请使用QQ平台登录该账户'));
					break;
				case MEMBER_TYPE_WEIBO:
					$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '您是微博注册用户，请使用微博平台登录该账户'));
					break;
				case MEMBER_TYPE_WEIXIN:
					$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '您是微信注册用户，请使用微信平台登录该账户'));
					break;
				default:
					$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '该手机号还未注册，请先注册'));
			}
		} elseif ($type == 'bind') {
			if ($member['type'] != MEMBER_TYPE_WEIBO && $member['type'] != MEMBER_TYPE_QQ && $member['type'] != MEMBER_TYPE_WEIXIN)
				$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '非绑定用户'));

			if (!empty($member['mobile']))
				$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '手机号已绑定'));

			if (!empty($mobile_user))
				$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '手机号已存在'));
		} else {
				$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));
		}

		$now_time = NOW_TIME;
		$redis = getRedisEx(self::cache_config);
		$value = $redis->json_get(MEMBER_VERIFYCODE_PREFIX . $member['id']);
		if (!empty($value)) {
			if ($value['time'] > ($now_time - 60))
				$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '频繁请求'));
		}

		// 生成验证码
		$value['mobile'] = $mobile;
		$value['type'] = $type;
		$value['time'] = $now_time;
		$value['code'] = makeRandomString(6, 'number');

		// 发短信
		$ret = sendSMS($mobile, '您的验证码为' . $value['code'] . '，15分钟内有效。祝您愉快！', 1);
		if (!$ret)
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '验证码发送失败'));

		$redis->setex(MEMBER_VERIFYCODE_PREFIX.$member['id'], C('MEMBER_VERIFYCODE_CACHE_TIME'), json_encode($value));		
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, json_decode('{}')));
	}

	/**
	 * 比对验证码，绑定手机接口
	 */
	public function verifycodeAction() {
		$token = I('post.token', '');
		$code =  I('post.code', '');

		// 参数检查
		if (empty($token) || empty($code))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$user = verifyToken($token, true);
		if (empty($user))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));

		if ($user['type'] != MEMBER_TYPE_WEIBO && $user['type'] != MEMBER_TYPE_QQ && $user['type'] != MEMBER_TYPE_WEIXIN)
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '非注册客户'));

		if (!empty($user['mobile']))
			$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '手机号已绑定'));

		// 校验验证码
		$redis =	getRedisEx(self::cache_config);
		$value = $redis->json_get(MEMBER_VERIFYCODE_PREFIX . $user['id']);
		if (empty($value) || $value['code'] != $code)
			$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '验证码错误，请重新输入'));

		$data = array('id' => $user['id']);
		$data['mobile'] = $value['mobile'];
		D('User')->save($data);

		$redis->del(MEMBER_VERIFYCODE_PREFIX . $user['id']);
		
		$redis->del(MEMBER_INFO_PREFIX . $user['id']);

		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, array('mobile' => $value['mobile'])));
	}

	/**
	 * 绑定clientid接口
	 */
	public function bindclientidAction() {
		$token = I('post.token', '');
		$clientid =  I('post.clientid', '');

		// 参数检查
		if (empty($token) || empty($clientid))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$user = verifyToken($token, true);
		if (empty($user))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));

		// 更新状态
		$this->upload_status($user['device_id'], $user['id']);

		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, json_decode('{}')));
	}

	/**
	 * 上传头像接口
	 */
	public function uploadavatarAction() {
		$token = I('post.token', '');
		$avatar_base64 =  I('post.avatar', '');

		// 参数检查
		if (empty($token) || empty($avatar_base64))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));
		
		$user = verifyToken($token, true);
		if (empty($user))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));

		if ($user['type'] == MEMBER_TYPE_GUEST)
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '用户类型错误'));

		// 上传头像
		$avatar = base64_decode($avatar_base64);
		if (empty($avatar))
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '头像上传失败。'));

		$avatar_file = $this->uploadUpyun($avatar, self::UPLOAD_PARAM_DATA, self::UPLOAD_PATH.$user['id']);
		if (empty($avatar_file))
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '头像上传失败'));

		$data['id'] = $user['id'];
		$data['avatar'] = $avatar_file;
		D('user')->save($data);
		
		$userRedis = getRedisEx(self::cache_config);
		$userRedis->del(MEMBER_INFO_PREFIX. $user['id']);

		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, '头像上传成功'));
	}

	/**
	 * 更新密码
	 */
	public function updatepasswordAction() {
		$token = I('post.token', '');
		$password = trim(I('post.password', ''));
		$code = I('post.code', '');

		// 验证参数
		if (empty($token) || empty($password) || empty($code))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$guest = verifyToken($token, true);
		if (empty($guest))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));

		if ($guest['type'] != MEMBER_TYPE_GUEST)
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '非游客用户'));

		$redis = getRedisEx(self::cache_config);
		$value = $redis->json_get(MEMBER_VERIFYCODE_PREFIX . $guest['id']);
		if (empty($value) || $value['code'] != $code)
			$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '更改密码失败'));

		// 修改口令
		$where = array('mobile' => $value['mobile']);
		D('User')->where($where)->setField('password', substr(sha1($password),0,32));

		$redis->del(MEMBER_VERIFYCODE_PREFIX . $guest['id']);
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, json_decode('{}')));
	}

	/**
	 * 注册检查
	 */
	public function checkAction() {
		$type = I('post.type', '');
		$key=  I('post.key', '');

		// 参数检查
		if (empty($type) || empty($key))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		if ($type == 'mobile') {
			$where = array('mobile' => $key);
		} elseif ($type == 'name') {
			$where = array('name' => $key);
		} else {
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));
		}

		// 
		$userModel = D('User');
		if ($userModel->where($where)->find())
			$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '用户已存在'));
		else
			$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, json_decode('{}')));
	}

	/**
	 * 手机注册接口
	 */
	public function mobileregisterAction() {
		$token = I('post.token', '');
		$password = trim(I('post.password', ''));
		$code = I('post.code', '');
		$name = trim(I('post.name', ''));
		$client_ip = get_client_ip(1);
		
		// 参数检测
		if (empty($token) || empty($password) || empty($code) || empty($name))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));

		$guest = verifyToken($token, true);
		if (empty($guest))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));

		if ($guest['type'] != MEMBER_TYPE_GUEST)
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '用户类型错误'));

		// 检查验证码
		$redis = getRedisEx(self::cache_config);
		$value = $redis->json_get(MEMBER_VERIFYCODE_PREFIX . $guest['id']);
		if (empty($value) || $value['code'] != $code)
			$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '验证码错误，请重新输入'));		

		// 加锁
		if (lock_action($token) == false)
			$this->ajaxReturn(responseError(RETURN_STATUS_REPEAT_ERROR, '请求过于频繁'));

		// 过滤包含公牛的昵称
		$gong_pos = strpos($name, '公');
		$niu_pos = strrpos($name, '牛');
		if ($gong_pos !== false && $niu_pos !== false) {
			if ($niu_pos > $gong_pos) {
				unlock_action($token);
				$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '用户名已存在'));
			}
		}

		// 检查手机和用户名
		$userModel = D('User');
		$where = array('name' => $name);
		$where['mobile'] = $value['mobile'];
		$where['_logic'] = 'OR';
		$user = $userModel->field('name,mobile')->where($where)->find();
		if (!empty($user)) {
			if (strcmp($value['mobile'], $user['mobile']) == 0) {
				unlock_action($token);
				$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '手机号已存在'));
			} else {
				unlock_action($token);
				$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '用户名已存在'));
			}
		}

		// 添加用户
		$data = array('login_type' => MEMBER_TYPE_MOBILE);
		$data['user_type'] = 'user';
		$data['user_level'] = 0;
		$data['status'] = MEMBER_STATUS_NORMAL;
		$data['ctime'] = date('Y-m-d H:i:s', NOW_TIME);
		$data['cdevice_id'] = $guest['device_id'];
		$data['cip'] = $client_ip;
		$data['ltime'] = $data['ctime'];
		$data['ldevice_id'] = $data['cdevice_id'];
		$data['lip'] = $data['cip'];		
		$data['utime'] = $data['ctime'];
		$data['udevice_id'] = $data['cdevice_id'];
		$data['uip'] = $data['cip'];
		$data['mobile'] = $value['mobile'];
		$data['password'] = substr(sha1($password),0,32);
		$data['name'] = $name;
		$data['avatar'] = $this->_getRandomAvatar();
		$user_id = $userModel->add($data);
		if (empty($user_id)){
			unlock_action($token);
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '用户创建失败'));
		}

		$redis->del(MEMBER_VERIFYCODE_PREFIX . $guest['id']);
		unlock_action($token);
		
		$return = array('uid' => (int)$user_id);
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, $return));
	}

	/**
	 * 自推广手机快速注册接口
	 */
	public function pppregisterAction() {
		$token = I('post.token', '');
		$mobile = trim(I('post.mobile', ''));
		$password = I('post.password', '');
		$name = trim(I('post.name', ''));
		$client_ip = get_client_ip(1);
		
		// 参数检测
		if (empty($token) || empty($mobile) || empty($password) || empty($name))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));
		
		$guest = verifyToken($token, true);
		if (empty($guest))
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));
		
		if ($guest['type'] != MEMBER_TYPE_GUEST)
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '用户类型错误'));
	
		// 加锁
		if (lock_action($token) == false)
			$this->ajaxReturn(responseError(RETURN_STATUS_REPEAT_ERROR, '请求过于频繁'));

		// 判断手机是否注册
		$userModel = D('User');
		$where = array('mobile' => $mobile);
		$where['name'] = $name;
		$where['_logic'] = 'OR';
		$member = $userModel->where($where)->field('id')->find();
		if ($member) {
			unlock_action($token);
			$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '手机号或用户名已存在'));
		}

		// 判断是自推广用户
		$pppstatus = $this->_checkInPpp($mobile, $password);
		if ($pppstatus === false) {
			unlock_action($token);
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '用户已存在'));
		}

		// 添加用户
		$data = array('login_type' => MEMBER_TYPE_MOBILE);
		$data['user_type'] = 'user';
		$data['user_level'] = 0;
		$data['ctime'] = date('Y-m-d H:i:s', NOW_TIME);
		$data['cdevice_id'] = $guest['device_id'];
		$data['cip'] = $client_ip;
		$data['ltime'] = $data['ctime'];
		$data['ldevice_id'] = $data['cdevice_id'];
		$data['lip'] = $data['cip'];
		$data['utime'] = $data['ctime'];
		$data['udevice_id'] = $data['cdevice_id'];
		$data['uip'] = $data['cip'];
		$data['mobile'] = $mobile;
		$data['password'] = substr(sha1($password),0,32);
		$data['name'] = $name;
		$data['avatar'] = $this->_getRandomAvatar();
		$uid = $userModel->add($data);
		if (empty($uid)) {
			\Think\Log::write('写入数据库错误，手机号：'.$mobile);
			unlock_action($token);
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '系统错误'));
		}

		// 通知自推广系统用户注册成功
		$post = array('mobile' => $mobile);
		$post['passwd'] = $password;
		$post['userid'] = $uid;
		$post['uname'] = $name;
		$status = \Org\Net\Http::fsockopenDownload(C('PPP_SYSTEM_URL').'/ppp/inviteAction/Login', array('post' => $post));
		if (empty($status))
			\Think\Log::write('调取ppp:Login方法失败,uid：'.$uid);

		unlock_action($token);
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, json_decode('{}')));
	}



	/**
	 * 给内部提供的手机注册接口（基金使用）
	 * @param unknown $name
	 * @param unknown $mobile
	 * @param unknown $password
	 */
	public function _mobileregister($name, $mobile, $password) {
		$client_ip = get_client_ip(1);

		// 检查手机和用户名
		$userModel = D('User');
		$where = array('name' => $name);
		$where['mobile'] = $mobile;
		$where['_logic'] = 'OR';
		$user = $userModel->field('name,mobile')->where($where)->find();
		if (!empty($user)) {
			if (strcmp($mobile, $user['mobile']) == 0) {
				$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '手机号已存在'));
			} else {
				$this->ajaxReturn(responseError(RETURN_STATUS_EXIST_ERROR, '昵称已存在'));
			}
		}
		
		// 添加用户
		$data = array('login_type' => MEMBER_TYPE_MOBILE);
		$data['user_type'] = 'user';
		$data['user_level'] = 0;
		$data['status'] 		= MEMBER_STATUS_NORMAL;
		$data['ctime'] 		= date('Y-m-d H:i:s', NOW_TIME);
		$data['cdevice_id'] 	= 0;
		$data['cip'] 			= $client_ip;
		$data['ltime'] 		= $data['ctime'];
		$data['ldevice_id'] 	= $data['cdevice_id'];
		$data['lip'] 			= $data['cip'];
		$data['utime'] 		= $data['ctime'];
		$data['udevice_id'] 	= $data['cdevice_id'];
		$data['uip'] 			= $data['cip'];
		$data['mobile'] 		= $mobile;
		$data['password'] 	= substr(sha1($password),0,32);
		$data['name'] 			= $name;
		$data['avatar'] 		= $this->_getRandomAvatar();
		$user_id = $userModel->add($data);
		if ($user_id == false)
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '用户创建失败'));

		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, array('user_id'=>(int)$user_id)));
	}
	
	/**
	 * 更新密码(供内部基金使用)
	 */
	public function _updatepassword($mobile, $newpassword, $oldpassword=null) {
		$where = array('mobile' => $mobile);
		if ($oldpassword != null) 
			$where['password'] = substr(sha1($oldpassword),0,32);

		$model = D('User');
		$data = $model->where($where)->find();
		if (empty($data)) 
			$this->ajaxReturn(responseError(RETURN_STATUS_NOT_EXIST_ERROR, '手机或密码输入错误'));
		
		// 修改密码
		$model->where($where)->setField('password', substr(sha1($newpassword),0,32));
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, json_decode('{}')));
	}
	
	/**
	 * 编辑个人简介
	 */
	public function editDescriAction() {
		$token = I('post.token', '');
		$description = trim(I('post.description', ''));
		
		// 参数检测
		if (empty($token) || empty($description))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));
		
		$user = verifyToken($token, true);
		if (empty($user) || $user['login_type'] == 'guest')
			$this->ajaxReturn(responseError(RETURN_STATUS_AUTH_ERROR, '身份认证失败，请重新登录'));
		
		$data = array();
		$data['id'] = $user['id'];
		$data['intro'] = $description;
		D('User')->save($data);
		$redis = getRedisEx('USER_CACHE');
		$redis->del(MEMBER_INFO_PREFIX . $user['id']);
		
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, json_decode('{}')));
	}



	/**
	 * 网站上传头像接口
	 */
	public function webuploadavatar($userid,$avatar) {
		

		// 参数检查
		if (empty($userid) || empty($avatar))
			$this->ajaxReturn(responseError(RETURN_STATUS_PARAM_ERROR, '参数错误'));
	
		$user = getMemberInfo($userid);
		if ($user['type'] == MEMBER_TYPE_GUEST)
			$this->ajaxReturn(responseError(RETURN_STATUS_TYPE_ERROR, '用户类型错误'));

		$strindex = strpos($avatar,',');
		$attach_url = substr($avatar, ($strindex+1));
		// 上传头像
		
		$avatar_file = $this->uploadUpyun(base64_decode($attach_url), self::UPLOAD_PARAM_DATA, self::UPLOAD_PATH.$userid);
		if (empty($avatar_file))
			$this->ajaxReturn(responseError(RETURN_STATUS_SYSTEM_ERROR, '头像上传失败'));
	
		$data['id'] = $user['id'];
		$data['avatar'] = $avatar_file;
		D('Api/User')->save($data);
	
		$userRedis = getRedisEx(self::cache_config);
		$userRedis->del(MEMBER_INFO_PREFIX. $user['id']);
	
		$this->ajaxReturn(responseSucc(RETURN_STATUS_OK, '头像上传成功'));
	}
	
	
	
	
	
	
	
	
	
	
}