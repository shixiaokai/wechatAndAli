<script src="http://res.wx.qq.com/open/js/jweixin-1.0.0.js"></script>
<script>
    wx.config({
        debug: false,
        appId: '<?php echo $signPackage["appId"];?>',
        timestamp: <?php echo $signPackage["timestamp"];?>,
        nonceStr: '<?php echo $signPackage["nonceStr"];?>',
        signature: '<?php echo $signPackage["signature"];?>',
        jsApiList: [
            'configWXDeviceWiFi'
        ]
    });
    wx.ready(function () {
        // 在这里调用 API
        wx.checkJsApi({
            jsApiList: ['configWXDeviceWiFi'],
            success: function(res) {
                WeixinJSBridge.invoke('configWXDeviceWiFi', {}, function(res){
                    var err_msg = res.err_msg;
                    if(err_msg == 'configWXDeviceWiFi:ok') {
//                        alert('config success');
                        return;
                    } else {
                        alert('config fail');
                    }
                });
            }
        });
    });
</script>
