<?php
namespace Web\Controller;
use Think\Controller;
use Rada\Trade\Trade;
use Rada\User\User;
use Rada\Trade\TradeConst;

class TestController extends Controller {
    public function _initialize() {
        if (ACTION_NAME != 'login' && ACTION_NAME !='register') {
            $session = session('login');
            if (empty($session))
                $this->redirect('Test/login');
            
            $user_id = $session['id'];
            $user = new User();
            $this->assign('user_info', $user->getUserInfo($user_id));
        }
    }
    
	public function registerAction() {
	    if (IS_POST) {
	        $email = I('post.email', '');
	        $password = I('post.password', '');
	        $recommend_email = I('post.recommend_email');
	        $recommendleader_email = I('post.recommendleader_email');
	        $area = I('post.area', 'A');
	        $safe_password = I('post.safe_password');
	        $mobile = I('post.mobile');
	        
	        // verify
	        $recommend_userid = $recommend_leaderid = 0;
	        $user = new User();
	        if (!empty($recommend_email)) {
	           $recommend_info = $user->verifyEmail($recommend_email);
	           if (empty($recommend_info))
	               $this->error('推荐人不存在');
	           
	           $recommend_userid = $recommend_info['id'];

	           if (!empty($recommendleader_email)) {
	               $recommendleader_info = $user->verifyEmail($recommendleader_email);
	               if (empty($recommendleader_info))
	                   $this->error('推荐人领导不存在');
	               
	               if ($recommend_info['recommend_userid'] != $recommendleader_info['id'])
	                   $this->error('推荐人领导不正确');
	           }
	           
	           $recommend_leaderid = $recommendleader_info['id'];
	        }
	        
	        $return = $user->register($email, $password, $area, $safe_password, $mobile, $recommend_userid, $recommend_leaderid);
	        if (!$return)
	            $this->error('注册失败');
	        
	        $this->success('注册成功');
	            
	    } else {
	       $this->display();
	    }
	}
	
	public function loginAction() {
	    if (IS_POST) {
	        $email = I('post.email');
	        $password = I('post.password');
	         
	        if (empty($email) || empty($password))
	            $this->error('参数错误');
	         
	        $where = array(
	            'email'    => $email,
	            'password' => $password
	        );
	        $info = D('User')->where($where)->find();
	        if (empty($info))
	            $this->error('用户不存在');
	         
	        session('login', $info);
	        $this->redirect('Test/index');
	        return ;
	    } else {
	        $this->display();
	    }
	}
	
	/**
	 * 主页
	 */
	public function indexAction() {
	    $session = session('login');
	    if (empty($session))
	        $this->redirect('Test/login');
	
	    $user_id = $session['id'];
	    $account = D('Account')->where(array('user_id'=>$user_id))->find();
	    $this->assign('account', $account);
	    
	    $trade = new Trade();
	    $order = $trade->getUserUnfinishedOrder($user_id);
	    $this->assign('order',$order);
	    $this->assign('user', $session);

	    $buy_trades = $sell_trades = array();
	    $temp_btrades = $trade->listTrade(TradeConst::TRADE_OP_TYPE_BUY);
	    $temp_strades = $trade->listTrade(TradeConst::TRADE_OP_TYPE_SELL);
	    if (!empty($temp_btrades)) {
	        foreach ($temp_btrades as $tinfo) {
	            array_push($buy_trades, explode('|', $tinfo));
	        }
	    }
	    if (!empty($temp_strades)) {
	        foreach ($temp_strades as $tinfo) {
	            array_push($sell_trades, explode('|', $tinfo));
	        }
	    }
	    
	    $this->assign('buy_trades', $buy_trades);
	    $this->assign('sell_trades', $sell_trades);
	    $this->display();
	}
	
	public function logoutAction() {
	    session('login', null);
	    $this->redirect('Test/login');
	}
	
	/**
	 * 我要买
	 */
	public function buyAction() {
        $session = session('login');
        if (empty($session))
            $this->redirect('Test/login');
        
        $user_id = $session['id'];
        
	    if (IS_POST) {
	        $num = I('post.num');
	        if (empty($num))
	            $this->error('请输入买数量');
	        
	        $trade = new Trade();
	        $trade->makeTrade($user_id, 'buy', $num);
	        $this->success('success');
	    } else {
	        $account = D('Account')->where(array('user_id'=>$user_id))->find();
	        $this->assign('account', $account);
	        $this->display();
	    }
	}
	
	/**
	 * 我要卖
	 */
	public function sellAction() {
	    $session = session('login');
	    if (empty($session))
	        $this->redirect('Test/login');
	    
	    $user_id = $session['id'];
	    $user_sdk = new User();
	    $user_info = $user_sdk->getUserInfo($user_id, true);
	    if (empty($user_info['bank_info']))
	        $this->redirect('Test/makeBankInfo');
	    
	    if (IS_POST) {
	        $num = I('post.num');
	        if (empty($num))
	            $this->error('请输入卖数量');
	         
	        $trade = new Trade();
	        $trade->makeTrade($user_id, 'sell', $num);
	        $this->success('success');
	    } else {
	        $account = D('Account')->where(array('user_id'=>$user_id))->find();
	        $this->assign('account', $account);
	        $this->display();
	    }
	}
	
	/**
	 * 完善银行信息
	 */
	public function makeBankInfoAction() {
	    $session = session('login');
	    if (empty($session))
	        $this->redirect('Test/login');
	    $user_id = $session['id'];
	    
	    if (IS_POST) {
	        $bank_name = I('post.bank_name');
	        $card_num = I('post.card_num');
	        $bank_info = array(
	            'bank_name'=> $bank_name,
	            'card_num' => $card_num
	        );
	        $update = array('bank_info'=>json_encode($bank_info));
	        D('User')->where(array('id'=>$user_id))->save($update);
	        $this->redirect('Test/index');
	    } else {
	        $this->display();
	    }
	}
	
	public function unfinishedOrderAction() {
	    $session = session('login');
	    if (empty($session))
	        $this->redirect('Test/login');
	     
	    $user_id = $session['id'];
	    $trade = new Trade();
	    $orders = $trade->getUserUnfinishedOrder($user_id);
	    
	    $buy_orders = json_decode($orders['buy'], true);
	    $sell_orders = json_decode($orders['sell'], true);
	   
	    $b_orders = $s_orders = array();
	    if (!empty($buy_orders)) {
	        foreach ($buy_orders as $o_id) {
	            array_push($b_orders, $trade->getOrderInfo($o_id));
	        }
	    }
	    if (!empty($sell_orders)) {
	        foreach ($sell_orders as $o_id) {
	            array_push($s_orders, $trade->getOrderInfo($o_id));
	        }
	    }
	    
	    $this->assign('buy_orders', $b_orders);
	    $this->assign('sell_orders', $s_orders);
	    $this->display();
	}
	
	/**
	 * 撮合
	 */
	public function cuoheAction() {
	    $trade = new Trade();
	    while (time() < NOW_TIME + 55) {
	        $trade->cuohe();
	        sleep(1);
	    }
	}
	
	
	
	public function uploadAction() {
	    $session = session('login');
	    if (empty($session))
	        $this->redirect('Test/login');
	    
	    $user_id = $session['id'];
	    
	    if (IS_POST) {
	        $order_id = I('get.order_id','');
	        if (empty($order_id))
	            $this->error('参数错误');

	        $trade = new Trade();
	        $order_info = $trade->getOrderInfo($order_id);
	        if (empty($order_info) || $order_info['buy_uid']!=$user_id || !empty($order_info['photo_id']))
	            $this->error('系统错误');
	        
	        $return = $trade->attach($user_id, $order_id);
	        $this->success('上传成功',U('Test/index'));
	        return;
	    } else {
	        $this->display();
	    }
	}
	
	public function viewPicAction() {
	    $session = session('login');
	    if (empty($session))
	        $this->redirect('Test/login');
	     
	    $user_id = $session['id'];
	     
	    $trade = new Trade();
	    $order_id = I('get.order_id');
	    if (empty($order_id))
	        $this->error('参数错误');
	    $order_info = $trade->getOrderInfo($order_id);
	    if (empty($order_info) || empty($order_info['photo_id']))
	        $this->error('系统错误');
	    
	    $photo = D('Attach')->find($order_info['photo_id']);
	    $this->assign('photo', $photo);
	    $this->display();
	}
	
	public function testAction() {
	    $redis = getRedisEx('TRADE_CACHE');
	    $index = $redis->lGet('list',1);
	    dump($index);
	}
}