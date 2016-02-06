<?php
namespace Rada\Trade;

use Rada\Exception\RadaException;
/**
 * Trade类
 * @author 
 *
 */
class Trade {
    
    /**
     * 生成交易需求
     * @param unknown $user_id
     * @param unknown $type
     * @param unknown $num
     */
    public function makeTrade($user_id, $type, $num) {
        if (empty($user_id) || empty($num))
            return false;
        $redis = getRedisEx('TRADE_CONFIG');
        $data = sprintf('%d|%d|%s', $user_id, $num, NOW_TIME);
        switch ($type) {
            case TradeConst::TRADE_OP_TYPE_BUY:
                $redis->lPush(TradeConst::TRADE_LIST_BUY, $data);
                break;
            case TradeConst::TRADE_OP_TYPE_SELL:
                $redis->lPush(TradeConst::TRADE_LIST_SELL, $data);
                break;
            default:
                throw new RadaException('参数错误');
        }
    }
    
    public function listTrade($type) {
        $redis = getRedisEx('TRADE_CACHE');
        switch($type) {
            case TradeConst::TRADE_OP_TYPE_BUY:
                $trades = $redis->lrange(TradeConst::TRADE_LIST_BUY, 0, -1);
                break;
            case TradeConst::TRADE_OP_TYPE_SELL:
                $trades = $redis->lrange(TradeConst::TRADE_LIST_SELL, 0, -1);
                break;
            default:
                throw new RadaException('参数错误');
        }
        return $trades;
    }
    
    /**
     * 得到用户当前未完成的订单
     * @param unknown $user_id
     */
    public function getUserUnfinishedOrder($user_id) {
        $redis = getRedisEx('TRADE_CACHE');
        $key = TradeConst::UNFINISHED_ORDER_PREFIX.$user_id;
        $redis_data = $redis->hGetAll($key);
        if (empty($redis_data)) {
            $buy_map = array(
                'buy_uid' => $user_id,
                'status'  => TradeConst::TRADE_STATUS_GOING
            );
            $buy_orders = D('Order')->where($buy_map)->getField('id', true);
            
            $sell_map = array(
                'sell_uid'  => $user_id,
                'status'    => TradeConst::TRADE_STATUS_GOING
            );
            $sell_orders = D('Order')->where($sell_map)->getField('id', true);
            
            if (empty($buy_orders))
                $buy_orders = array();
            if (empty($sell_orders))
                $sell_orders = array();
            
            $redis_data = array(
                TradeConst::TRADE_OP_TYPE_BUY  => json_encode($buy_orders),
                TradeConst::TRADE_OP_TYPE_SELL => json_encode($sell_orders)
            );
            $redis->hMset($key, $redis_data);
            $redis->expire($key, 86400);
        }
        return $redis_data;
    }
    
    /**
     * 给用户加缓存加订单
     * @param unknown $user_id
     * @param unknown $type
     * @param unknown $order_id
     */
    private function addUserOrder($user_id, $type, $order_id) {
        $redis = getRedisEx('TRADE_CACHE');
        $redis->del(TradeConst::UNFINISHED_ORDER_PREFIX.$user_id);
        $this->getUserUnfinishedOrder($user_id);
    }
    
    /**
     * 生成订单
     * @param unknown $user_id
     * @param unknown $num
     * @param string $op_type
     * @throws RadaException
     * @return boolean
     */
    public function makeOrder($buy_uid, $sell_uid, $num) {
        $lock_key = 'makeOrder'.$buy_uid.$sell_uid;
        // 加锁
        $lock = lock_action($lock_key);
        if (!$lock)
            throw new RadaException('系统繁忙稍后再试');
        
        $order_info = array(
            'buy_uid'       => $buy_uid,
            'sell_uid'      => $sell_uid,
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

        $this->addUserOrder($buy_uid, TradeConst::TRADE_OP_TYPE_BUY, $res);
        $this->addUserOrder($sell_uid, TradeConst::TRADE_OP_TYPE_SELL, $res);
        unlock_action($lock_key);
        return true;
    }
    
    /**
     * 根据订单id取订单信息
     * @param unknown $order_id
     * @throws RadaException
     */
    public function getOrderInfo($order_id) {
        $order_info = D('Order')->find($order_id);
        if (empty($order_info))
            throw new RadaException('订单不存在');
        
        return $order_info;
    }
    
    /**
     * 撮合程序
     */
    public function cuohe() {
        $redis = getRedisEx('TRADE_CACHE');
        $buy_size = $redis->lSize(TradeConst::TRADE_LIST_BUY);
        if (empty($buy_size))
            return true;
        
        $sell_size = $redis->lSize(TradeConst::TRADE_LIST_SELL);
        if (empty($sell_size))
            return true;
        
        $pop_buy = $redis->rPop(TradeConst::TRADE_LIST_BUY);
        list($buy_uid, $buy_num, $buy_date) = explode('|', $pop_buy, 3);
        
        $pop_sell = $redis->rPop(TradeConst::TRADE_LIST_SELL);
        list($sell_uid, $sell_num, $sell_date) = explode('|', $pop_sell, 3);
        
        if ($sell_uid == $buy_uid) {
            $this->makeTrade($buy_uid, TradeConst::TRADE_OP_TYPE_BUY, $buy_num);
            $this->makeTrade($sell_uid, TradeConst::TRADE_OP_TYPE_SELL, $sell_num);
            return true;
        }
        
        $buy_num = (int)$buy_num;
        $sell_num = (int)$sell_num;
        if ($buy_num < $sell_num) {
            $order_id = $this->makeOrder($buy_uid, $sell_uid, $buy_num);
            $this->makeTrade($sell_uid, TradeConst::TRADE_OP_TYPE_SELL, $sell_num-$buy_num);
        } elseif ($buy_num == $sell_num) {
            $order_id = $this->makeOrder($buy_uid, $sell_uid, $buy_num);
        } elseif ($buy_num > $sell_num) {
            $order_id = $this->makeOrder($buy_uid, $sell_uid, $sell_num);
            $this->makeTrade($buy_uid, TradeConst::TRADE_OP_TYPE_BUY, $buy_num-$sell_num);
        }
        return true;
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
}