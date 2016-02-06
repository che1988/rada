<?php
namespace Admin\Controller;
use Think\Controller;
class UserController extends Controller {
    public function listAction(){
        $lists = D('User')->select();
        $this->assign('lists', $lists);
        $this->display();
    }
}