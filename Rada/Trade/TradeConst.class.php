<?php
namespace Rada\Trade;
/**
 * TradeExt类
 * @author 
 *
 */
class TradeConst {
    
    /**
     * 买信息前缀
     * @var unknown
     */
    const TRADE_INFO_BUY_PRIFIX = 'trade:info_buy:';
    
    /**
     * 卖信息前缀
     * @var unknown
     */
    const TRADE_INFO_SELL_PREFIX = 'trade:info_sell:';
    
    /**
     * 用户交易信息前缀
     * @var unknown
     */
    const TRADE_USER_STATUS_PREFIX = 'user:trade_status:';
    
    /**
     * 买
     * @var unknown
     */
    const TRADE_OP_TYPE_BUY = 'buy';
    
    /**
     * 卖
     * @var unknown
     */
    const TRADE_OP_TYPE_SELL = 'sell';
    
    /**
     * 交易信息状态（开）
     * @var unknown
     */
    const TRADE_STATUS_OPEN = 1;
    
    /**
     * 交易信息状态（进行中）
     * @var unknown
     */
    const TRADE_STATUS_GOING = 2;
    
    /**
     * 交易信息状态（关闭）
     * @var unknown
     */
    const TRADE_STATUS_CLOSE = 3;

    /**
     * 交易信息状态（完成）
     * @var unknown
     */
    const TRADE_STATUS_DONE = 4;
}