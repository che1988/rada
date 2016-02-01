<?php
namespace Rada\Trade;
/**
 * Account类
 * @author 
 *
 */
class Account {
    
    /**
     * 生成账户
     */
    public function makeAccount() {
        $data = array(
            'user_id'   => $user_id,
            'coin'      => $coin,
            'coin_tic'  => $coin_tic,
            'status'    => $status
        );
    }
    
    /**
     * 增加流水
     */
    public function addHistory() {
        $data = array(
            'user_id'   => $user_id,
            'type'      => $type,
            'coin'      => $coin,
            'coin_tic'  => $coin_tic,
            'order_id'  => $order_id,
            'admin_info'=> $admin_info,
            'admin_id'  => $admin_id,
            'status'    => $status,
        );
    }
}