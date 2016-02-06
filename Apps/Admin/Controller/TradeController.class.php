<?php
namespace Admin\Controller;
use Think\Controller;
use Rada\Trade\Trade;
use Rada\Trade\TradeConst;

class TradeController extends Controller {
    
    public function listBuyTradesAction(){
        $buy_trades = array();
        $trade = new Trade();
        $temp_trades = $trade->listTrade(TradeConst::TRADE_OP_TYPE_BUY);
        if (!empty($temp_trades)) {
            foreach ($temp_trades as $tinfo) {
                array_push($buy_trades, explode('|', $tinfo));
            }
        }
        
        $this->assign('buy_trades', $buy_trades);
        $this->display();
    }
    
    public function listSellTradesAction() {
        $sell_trades = array();
        $trade = new Trade();
        $temp_trades = $trade->listTrade(TradeConst::TRADE_OP_TYPE_SELL);
        if (!empty($temp_trades)) {
            foreach ($temp_trades as $tinfo) {
                array_push($sell_trades, explode('|', $tinfo));
            }
        }
         
        $this->assign('sell_trades', $sell_trades);
        $this->display();
    }
    
    public function listordersAction() {
        $lists = D('Order')->select();
        $this->assign('lists', $lists);
        $this->display();
    }
}