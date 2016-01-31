<?php
namespace Rada\Trade;
/**
 * TradeExt类
 * @author 
 *
 */
class TradeExt {
    
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
}