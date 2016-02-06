var publicApi={
	  /*
     * tabs功能
     * tabs功能需要按照demo中的html嵌套关系，详细参考demo
     * 调用方法：publicApi.tabs("tabsId"),tabsId是tabs最外层的id名称
     */
    tabs:function(idname){
        var id="#"+idname;
		$(id+" .tabs_content").hide();
        $(id+" .tabs_content:eq(0)").show();
        $(id+" li").removeClass("cur");
        $(id+" li:eq(0)").addClass("cur");
        $(id).on("click",".tablist li",function(){
            var index=$(this).index();
            $(this).addClass("cur");
            $(this).siblings().removeClass("cur");
            $(id+" .tabs_content:eq("+index+")").show().siblings().hide();
        });
    }
}