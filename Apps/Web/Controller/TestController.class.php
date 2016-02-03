<?php
namespace Web\Controller;
use Think\Controller;
use Rada\Trade\Trade;

class TestController extends Controller {
	public function test1Action() {
	    $data = array(
	        'name'     => 'jack',
	        'status'   => 1
	    );
	    D('Test')->add($data);
	}
	
	public function transAction() {
	    D('Test')->startTrans();
	    
	    $data = array(
	        'name'     => 'jack',
	        'status'   => 1
	    );
	    $res_1 = D('Test')->add($data);
	    
	    $update = array(
	        'id'       => 1,
	        'name'     => 'jack',
	        'status'   => 1
	    );
	    
	    $res_2 = D('Test')->save($update);
	    if ($res_1 && $res_2) {
	        D('Test')->commit();
	    } else {
	        D('Test')->rollback();
	    }
	}
	
	public function attachAction() {
	    if (IS_POST) {
	        $trade = new Trade();
	        $return = $trade->attach(111);
	        dump($return);die;
	    } else {
	        echo <<<EOF
<form action="" method='post' enctype="multipart/form-data">
<input type='file' name='attach' />
	            <input type='submit'>
	        </form>
EOF;
	            
	    }
	}
}