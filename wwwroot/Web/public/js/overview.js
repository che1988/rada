$(document).ready(function() {
	$(document).click(function(){
		if($(".o_set_up i").hasClass('on')){
			$(".o_set_up ul").addClass('none');
			$(".o_set_up i").removeClass('on');
		}
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
	currency();
});
function currency() {
		var oUl= document.getElementById("currency");
		var aLi = oUl.getElementsByTagName("li");
		var disX = 0;
		var disY = 0;
		var minZindex = 1;
		var aPos =[];
		for(var i=0;i<aLi.length;i++){
			var t = aLi[i].offsetTop;
			var l = aLi[i].offsetLeft;
			aLi[i].style.top = t+"px";
			aLi[i].style.left = l+"px";
			aPos[i] = {left:l,top:t};
			aLi[i].index = i;
		}
		for(var i=0;i<aLi.length;i++){
			aLi[i].style.position = "absolute";
			aLi[i].style.margin = 0;
			setDrag(aLi[i]);
		}
		//拖拽
		function setDrag(obj){
			obj.onmouseover = function(){
				obj.style.cursor = "move";
			}
			obj.onmousedown = function(event){
				var scrollTop = document.documentElement.scrollTop||document.body.scrollTop;
				var scrollLeft = document.documentElement.scrollLeft||document.body.scrollLeft;
				obj.style.zIndex = minZindex++;
				//当鼠标按下时计算鼠标与拖拽对象的距离
				disX = event.clientX +scrollLeft-obj.offsetLeft;
				disY = event.clientY +scrollTop-obj.offsetTop;
				document.onmousemove=function(event){
					//当鼠标拖动时计算div的位置
					var l = event.clientX -disX +scrollLeft;
					var t = event.clientY -disY + scrollTop;
					obj.style.left = l + "px";
					obj.style.top = t + "px";
					/*for(var i=0;i<aLi.length;i++){
						aLi[i].className = "";
						if(obj==aLi[i])continue;//如果是自己则跳过自己不加红色虚线
						if(colTest(obj,aLi[i])){
							aLi[i].className = "active";
						}
					}*/
					for(var i=0;i<aLi.length;i++){
						aLi[i].className = "";
					}
					var oNear = findMin(obj);
					if(oNear){
						oNear.className = "active";
					}
				}
				document.onmouseup = function(){
					document.onmousemove = null;//当鼠标弹起时移出移动事件
					document.onmouseup = null;//移出up事件，清空内存
					//检测是否普碰上，在交换位置
					var oNear = findMin(obj);
					if(oNear){
						oNear.className = "";
						oNear.style.zIndex = minZindex++;
						obj.style.zIndex = minZindex++;
						startMove(oNear,aPos[obj.index]);
						startMove(obj,aPos[oNear.index]);
						//交换index
						oNear.index += obj.index;
						obj.index = oNear.index - obj.index;
						oNear.index = oNear.index - obj.index;
					}else{

						startMove(obj,aPos[obj.index]);
					}
				}
				clearInterval(obj.timer);
				return false;//低版本出现禁止符号
			}
		}
		//碰撞检测
		function colTest(obj1,obj2){
			var t1 = obj1.offsetTop;
			var r1 = obj1.offsetWidth+obj1.offsetLeft;
			var b1 = obj1.offsetHeight+obj1.offsetTop;
			var l1 = obj1.offsetLeft;

			var t2 = obj2.offsetTop;
			var r2 = obj2.offsetWidth+obj2.offsetLeft;
			var b2 = obj2.offsetHeight+obj2.offsetTop;
			var l2 = obj2.offsetLeft;

			if(t1>b2||r1<l2||b1<t2||l1>r2){
				return false;
			}else{
				return true;
			}
		}
		//勾股定理求距离
		function getDis(obj1,obj2){
			var a = obj1.offsetLeft-obj2.offsetLeft;
			var b = obj1.offsetTop-obj2.offsetTop;
			return Math.sqrt(Math.pow(a,2)+Math.pow(b,2));
		}
		//找到距离最近的
		function findMin(obj){
			var minDis = 999999999;
			var minIndex = -1;
			for(var i=0;i<aLi.length;i++){
				if(obj==aLi[i])continue;
				if(colTest(obj,aLi[i])){
					var dis = getDis(obj,aLi[i]);
					if(dis<minDis){
						minDis = dis;
						minIndex = i;
					}
				}
			}
			if(minIndex==-1){
				return null;
			}else{
				return aLi[minIndex];
			}
		}	
	}