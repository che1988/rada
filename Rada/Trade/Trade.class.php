<?php
namespace Rada\Trade;

use Rada\Exception\RadaException;
/**
 * Trade类
 * @author 
 *
 */
class Trade extends TradeExt {
    
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
                'status'    => array('in'=> array(TradeExt::TRADE_STATUS_OPEN, TradeExt::TRADE_STATUS_GOING))
            ))->select();
        if ($status) {
            unlock_action($lock_key);
            throw new RadaException('你当前有未结束的交易');
        }
        
        $order_num = substr(md5(makeRandomString(10)), 0, 20);
        $order_info = array(
            'order_num'     => $order_num,
            'user_id'       => $user_id,
            'op_type'       => $op_type,
            'num'           => $num,
            'account'       => 0,
            'status'        => TradeExt::TRADE_STATUS_OPEN,
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
                array('op_type'=>TradeExt::TRADE_OP_TYPE_BUY, 'status'=> TradeExt::TRADE_STATUS_OPEN)
            )->order('ctime asc')->select();
        
        $sell_orders = D('Order')->where(
                array('op_type'=>TradeExt::TRADE_OP_TYPE_SELL, 'status'=> TradeExt::TRADE_STATUS_OPEN)
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
    }
    
    /**
     * 成交订单
     * @param unknown $buy_id
     * @param unknown $sell_id
     */
    public function dealOrder($buy_id, $sell_id) {
        $res = D('Order')->where(array('id'=>$buy_id))->setField('status', TradeExt::TRADE_STATUS_GOING);
        $res = D('Order')->where(array('id'=>$sell_id))->setField('status', TradeExt::TRADE_STATUS_GOING);
        
        // 更新用户信息,通知用户去继续交易
    }
}