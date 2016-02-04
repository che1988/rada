<?php
namespace Web\Controller;
use Think\Controller;
use Rada\Trade\Trade;
use Rada\User\User;

class TestController extends Controller {
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
	 * 撮合
	 */
	public function cuoheAction() {
	    $trade = new Trade();
	    $trade->cuohe();
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
	    $order = $trade->checkUserOrderId($user_id);
	    $this->assign('order',$order);
	    $this->assign('user', $session);
	    $this->display();
	}
	
	public function uploadAction() {
	    $session = session('login');
	    if (empty($session))
	        $this->redirect('Test/login');
	    
	    $user_id = $session['id'];
	    
	    $trade = new Trade();
	    $order = $trade->checkUserOrderId($user_id);
	    if (empty($order['buy_order']))
	        $this->error('你当前没有可买订单');
	    
	    if (IS_POST) {
	        $trade = new Trade();
	        $return = $trade->attach($user_id, $order['buy_order']);
	        $this->redirect('Test/login');
	    } else {
	        echo <<<EOF
<form action="" method='post' enctype="multipart/form-data">
<input type='file' name='attach' />
	            <input type='submit'>
	        </form>
EOF;
	            
	    }
	}
	
	public function viewPicAction() {
	    $session = session('login');
	    if (empty($session))
	        $this->redirect('Test/login');
	     
	    $user_id = $session['id'];
	     
	    $trade = new Trade();
	    $order = $trade->checkUserOrderId($user_id);
	    if (empty($order['sell_order']))
	        $this->error('你当前没有可卖订单');
	    
	    $order_info = D('Order')->find($order['sell_order']);
	    if (empty($order_info['photo_id']))
	        $this->error('当前买家还未上传凭证');
	    
	    $photo = D('Attach')->find($order_info['photo_id']);
	    $this->assign('photo', $photo);
	    $this->display();
	}
}