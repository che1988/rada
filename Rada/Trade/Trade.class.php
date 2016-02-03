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
     * 生成订单
     * @param unknown $user_id
     * @param unknown $num
     * @param string $op_type
     * @throws RadaException
     * @return boolean
     */
    public function makeOrder($user_id, $num, $op_type='buy') {
        $lock_key = 'makeOrder'.$user_id;
        // 加锁
        $lock = lock_action($lock_key);
        if (!$lock)
            throw new RadaException('系统繁忙稍后再试');
        
        // 确定用户没有任何进行的交易
        $status = D('Order')->where(array(
                'user_id'   => $user_id,
                'op_type'   => 'buy',
                'status'    => array('in'=> array(TradeConst::TRADE_STATUS_OPEN, TradeConst::TRADE_STATUS_GOING))
            ))->select();
        if ($status) {
            unlock_action($lock_key);
            throw new RadaException('你当前有未结束的交易');
        }
        
        $order_info = array(
            'user_id'       => $user_id,
            'op_type'       => $op_type,
            'num'           => $num,
            'account'       => 0,
            'status'        => TradeConst::TRADE_STATUS_OPEN,
            'ctime'         => date('Y-m-d H:i:s', NOW_TIME),
            'relation_id'   => 0,
            'photo_id'      => 0, 
            'admin_id'      => 0,
            'admin_info'    => ''
        );
        
        $res = D('Order')->add($order_info);
        if (!$res) {
            unlock_action($lock_key);
            throw new RadaException('系统繁忙，稍后再试');
        }
        
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
        
        $buy_orders = D('Order')->where(
                array('op_type'=>TradeConst::TRADE_OP_TYPE_BUY, 'status'=> TradeConst::TRADE_STATUS_OPEN)
            )->order('ctime asc')->select();
        
        $sell_orders = D('Order')->where(
                array('op_type'=>TradeConst::TRADE_OP_TYPE_SELL, 'status'=> TradeConst::TRADE_STATUS_OPEN)
            )->order('ctime asc')->select();
        
        foreach ($buy_orders as $buy_order) {
            foreach ($sell_orders as $key => $sell_order) {
                if ($buy_order['num'] == $sell_order['num']) {
                    try {
                        $this->dealOrder($buy_order['id'], $sell_order['id']);
                        unset($sell_orders[$key]);
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
	public function attach($user_id, $base64_attach='') {
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
		
		$true_path = APP_PATH . '../Public' . $image['save_name'];
	
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
	
		$data['attach_id'] = $attach_id;
		$data['attach_url'] = C('WEIBA_ATTACH_IMAGE_PATH') . $attach['save_name'];
		return $data;
	
	}
	
	public function sellerConfirmAction() {
	    
	}
}