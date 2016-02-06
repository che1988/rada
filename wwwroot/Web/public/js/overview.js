$(document).ready(function() {
	$(document).click(function(){
		if($(".o_set_up i").hasClass('on')){
			$(".o_set_up ul").addClass('none');
			$(".o_set_up i").removeClass('on');
		}
		if($(".payment ul").hasClass('show')){
			$(".payment ul").removeClass('show');
		};
	});
	/*设置*/
    $(".o_set_up i").click(function(){
		$(this).addClass('on');
		$(this).parent('div.o_set_up').find('ul').removeClass('none');
		return false;
	});
	$(".o_set_up ul li").click(function(){
		$(this).parents('div.o_set_up').find('i').removeClass('on');
		$(this).parent('ul').addClass('none');
		return false;
	});
	/*货币资产*/
	$(".o_assets_left ul li .o_assets_left_show").click(function(){
		if($(this).parent('li').find('div.gateway').hasClass('none')){
			$(this).parent('li').find('div.gateway').removeClass('none');
		}else{
			$(this).parent('li').find('div.gateway').addClass('none');
		}
	});
	/*绑定手机*/
	$(".btn-cancel").click(function(){
		$(".mobileForm").slideDown();
		$(".usernames,.edit-account-btn-wrapper").hide();
	});
	$(".loading_cancel").click(function(){
		$(".mobileForm").slideUp();
		$(".usernames,.edit-account-btn-wrapper").show();
	});
	/*货币对,资产对*/
	$("#currency li a").click(function(){
		$(this).parent('li').remove();
		var c_height=$("#currency li").length,topPx=0;
		for(var i=0 ;i<c_height;i++){
            $("#currency li:eq("+i+")").animate({top:topPx});
			topPx += 45;
		}
	});
	/*邀请好友*/
	$(".activation").click(function(){
		$(this).parents('div.o_information_right').find('.tip').toggleClass('a_cur');
	});
	/*订单操作*/
	$(".payment em").click(function(){
		$(this).parent('.payment').find('ul').toggleClass('show');
		return false;
	});
	$(".payment ul li").click(function(){
		$(this).parents('.payment').find('ul').removeClass('show');
		return false;
	});
});