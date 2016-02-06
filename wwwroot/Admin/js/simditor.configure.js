$(function(){
	if($("#simditor").length>0){
		var editor = new Simditor({
		  textarea: $('#simditor'),
		  upload:{
	        url: '/File/Simditor',
	        params: {controller:global_controller_name},
	        connectionCount: 3,
	        leaveConfirm: '正在上传文件，如果离开上传会自动取消',
	    	},
		});	
	}
});