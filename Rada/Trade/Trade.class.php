<?php
namespace Rada\Trade;

use Rada\Exception\RadaException;
/**
 * Trade类
 * @author 
 *
 */
class Trade {
    
    public function makeTrade($user_id, $type, $num) {
        $redis = getRedisEx('TRADE_CONFIG');
        $data = array(
            'user_id'   => $user_id,
            'type'      => $type,
            'num'       => $num
        );
        $redis->hSet(TradeConst::TRADE_REQUIREMENT_INFO.$type, $user_id, json_encode($data));
        $redis->zAdd(TradeConst::TRADE_REQUIREMENT_ORDER.$type, NOW_TIME, $user_id);
    }
    
    /**
     * 生成订单
     * @param unknown $user_id
     * @param unknown $num
     * @param string $op_type
     * @throws RadaException
     * @return boolean
     */
    public function makeOrder($buy_id, $sell_id, $num) {
        $lock_key = 'makeOrder'.$buy_id.$sell_id;
        // 加锁
        $lock = lock_action($lock_key);
        if (!$lock)
            throw new RadaException('系统繁忙稍后再试');
        
        $order_info = array(
            'buy_uid'       => $buy_id,
            'sell_uid'      => $sell_id,
            'num'           => $num,
            'account'       => 0,
            'status'        => TradeConst::TRADE_STATUS_GOING,
            'ctime'         => date('Y-m-d H:i:s', NOW_TIME),
            'photo_id'      => 0, 
            'admin_id'      => 0,
            'admin_info'    => ''
        );
        
        $res = D('Order')->add($order_info);
        if (!$res) {
            unlock_action($lock_key);
            throw new RadaException('系统繁忙，稍后再试');
        }
        
        $redis = getRedisEx('TRADE_CACHE');
        $redis->hSet(TradeConst::TRADE_USER_STATUS_PREFIX.'buy', $buy_id, $res);
        $redis->hSet(TradeConst::TRADE_USER_STATUS_PREFIX.'sell', $sell_id, $res);
        
        unlock_action($lock_key);
        return true;
    }
    
    /**
     * 撮合程序
     */
    public function cuohe() {
        $lock_key = 'cuohe';
        $lock = lock_action($lock_key);
        if (!$lock)
            return true;
        
        $redis = getRedisEx('TRADE_CACHE');
        $buy_orders = $redis->hGetAll(TradeConst::TRADE_REQUIREMENT_INFO . 'buy');
        $sell_orders = $redis->hGetAll(TradeConst::TRADE_REQUIREMENT_INFO . 'sell');
        
        foreach ($buy_orders as $buy_order) {
            foreach ($sell_orders as $key => $sell_order) {
                $buy_order = json_decode($buy_order, true);
                $sell_order = json_decode($sell_order, true);
                if ($buy_order['num'] == $sell_order['num']) {
                    try {
                        $this->makeOrder($buy_order['user_id'], $sell_order['user_id'], $buy_order['num']);
                        unset($sell_orders[$key]);
                        $redis->hDel(TradeConst::TRADE_REQUIREMENT_INFO.'buy', $buy_order['user_id']);
                        $redis->hDel(TradeConst::TRADE_REQUIREMENT_INFO.'sell', $sell_order['user_id']);
                    } catch (\Exception $e) {
                        unlock_action($lock_key);
                        return false;
                    }
                }
            }
        }
        unlock_action($lock_key);
        return true;
    }
    
    /**
     * 撮合订单，事务更新状态为进行时
     * @param unknown $buy_id
     * @param unknown $sell_id
     */
    public function dealOrder($buy_id, $sell_id) {
        D('User')->startTrans();
        
        $map_1 = array('id'=>$buy_id, 'status'=>TradeConst::TRADE_STATUS_OPEN);
        $save_1 = array(
            'status'        => TradeConst::TRADE_STATUS_GOING,
            'relation_id'   => $sell_id
        );
        $res_1 = D('Order')->where($map_1)->save($save_1);
        
        $map_2 = array('id'=>$sell_id, 'status'=>TradeConst::TRADE_STATUS_OPEN);
        $save_2 = array(
            'status'        => TradeConst::TRADE_STATUS_GOING,
            'relation_id'   => $buy_id
        );
        $res_2 = D('Order')->where($map_2)->save($save_2);
        
        if (!$res_1 || !$res_2) {
            D('User')->rollback();
            throw new RadaException('系统错误');
        } else {
            D('User')->commit();
        }
        // 更新用户信息,通知用户去继续交易
        $redis = getRedisEx('TRADE_CACHE');
        $redis->set(TradeConst::TRADE_USER_STATUS_PREFIX.$buy_id, TradeConst::TRADE_STATUS_GOING);
        $redis->set(TradeConst::TRADE_USER_STATUS_PREFIX.$sell_id, TradeConst::TRADE_STATUS_GOING);
    }
    
    /**
	 * 附件上传
	 * @param unknown $user_id
	 * @param string $base64_attach
	 */
	public function attach($user_id,$order_id, $base64_attach='') {
		$user_id = (int)$user_id;
		// 获取附件图像
		if (!empty($base64_attach)) {
			$image['data'] = base64_decode($base64_attach);
			if (empty($image['data']))
				throw new RadaException('图片上传失败，换张试试吧。');
	
			$image['name'] = '';
			$image['ext'] = 'jpg';
			$image['type'] = 'image/jpeg';
			$image['size'] = strlen($image['data']);
		} else {
			if (empty($_FILES) || empty($_FILES['attach']))
				throw new RadaException('图片上传失败，换张试试吧。');
	
			$image = $_FILES['attach'];
	
			if ($image['error'] != 0)
				throw new RadaException('图片上传失败，换张试试吧。');
	
			$image['data'] = file_get_contents($image['tmp_name']);
			if (empty($image['data']))
				throw new RadaException('图片上传失败，换张试试吧。');
	
			$image['name'] = urldecode($image['name']);
			$image['ext'] = pathinfo($image['name'], PATHINFO_EXTENSION);
		}
	
		// 取图像信息
		$image_info = getimagesizefromstring($image['data']);
		if (empty($image_info))
			throw new RadaException('图片上传失败，换张试试吧。');
	
		$image['width'] = $image_info[0];
		$image['height'] = $image_info[1];
		if (empty($image['ext'])) {
			switch ($image_info[2]) {
				case 1: $image['ext'] = 'gif'; break;
				case 2: $image['ext'] = 'jpg'; break;
				case 3: $image['ext'] = 'png'; break;
				case 6: $image['ext'] = 'bmp'; break;
				default:
					throw new RadaException('图片上传失败，换张试试吧。');
			}
		}
		$image['hash'] = sha1($image['data']);
		$image['save_name'] = '/Attach/' . date('Y/m/d', NOW_TIME) . '/' . $image['hash'] . '.' . $image['ext'];
		
		$true_path = '.'. $image['save_name'];
	
		// 自动创建日志目录
		$attach_dir = dirname($true_path);
		if (!is_dir($attach_dir)) {
		    mkdir($attach_dir, 0755, true);
		}
		
		if (!file_put_contents($true_path, $image['data'], true))
			throw new RadaException('图片上传失败，换张试试吧。');
	
		// 入库
		$attach['user_id'] = $user_id;
		$attach['ctime'] = date('Y-m-d H:i:s', NOW_TIME);
		$attach['name'] = $image['name'];
		$attach['type'] = $image['type'];
		$attach['size'] = $image['size'];
		$attach['extension'] = $image['ext'];
		$attach['hash'] = $image['hash'];
		$attach['status'] = 1;
		$attach['save_name'] = $image['save_name'];
		$attach['width'] = $image['width'];
		$attach['height'] = $image['height'];
		
		$attach_id = D('Attach')->add($attach);
	
		if (empty($attach_id))
			throw new RadaException('图片上传失败，换张试试吧。');
		
		D('Order')->where(array('id'=>$order_id))->setField('photo_id',$attach_id);
	
		$data['attach_id'] = $attach_id;
		$data['attach_url'] = C('WEIBA_ATTACH_IMAGE_PATH') . $attach['save_name'];
		return $data;
	
	}
	
	/**
	 * 卖家确认发货
	 */
	public function sellerConfirmAction($sell_id) {
	    $sell_order = D('Order')->where(array('id'=>$sell_id))->select();
	    if (empty($sell_order))
	        throw new RadaException('系统错误');

	    $buy_id = $sell_order['relation_id'];
	    $buy_order = D('Order')->find($buy_id);
	    
	    D('Order')->startTrans();
	    
	    // 更新状态
	    $map_1 = array('id'=>$buy_id, 'status'=>TradeConst::TRADE_STATUS_OPEN);
	    $save_1 = array(
	        'status'        => TradeConst::TRADE_STATUS_DONE,
	        'relation_id'   => $sell_id
	    );
	    $res_1 = D('Order')->where($map_1)->save($save_1);
	    
	    $map_2 = array('id'=>$sell_id, 'status'=>TradeConst::TRADE_STATUS_OPEN);
	    $save_2 = array(
	        'status'        => TradeConst::TRADE_STATUS_DONE,
	        'relation_id'   => $buy_id
	    );
	    $res_2 = D('Order')->where($map_2)->save($save_2);
	    
	    // 划钱
	    $res_3 = D('Account')->where(array('user_id'=>$sell_order['user_id']))->setDec('coin', $sell_order['num']);
	    $res_4 = D('Account')->where(array('user_id'=>$buy_order['user_id']))->setInc('coin_tic', $sell_order['num']);
	    
	    // 给上级分钱
	    
	    if (!$res_1 || !$res_2 || $res_3 || $res_4) {
	        D('User')->rollback();
	        throw new RadaException('系统错误');
	    } else {
	        D('User')->commit();
	    }
	    // 更新用户信息,通知用户去继续交易
	    $redis = getRedisEx('TRADE_CACHE');
	    $redis->del(TradeConst::TRADE_USER_STATUS_PREFIX.$buy_id);
	    $redis->del(TradeConst::TRADE_USER_STATUS_PREFIX.$sell_id);
	}
	
	public function checkUserOrderId($user_id) {
	    $redis = getRedisEx('TRADE_CACHE');
	    $buy_order = $redis->hGet(TradeConst::TRADE_USER_STATUS_PREFIX.'buy',$user_id);
	    $sell_order = $redis->hGet(TradeConst::TRADE_USER_STATUS_PREFIX.'sell',$user_id);
	    return array(
	        'buy_order'    => $buy_order,
	        'sell_order'   => $sell_order
	    );
	}
}