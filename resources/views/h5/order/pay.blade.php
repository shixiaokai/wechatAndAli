<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>testWechatOrAliPay</title>
    <script type="text/javascript" src="/jquery/jquery-1.10.1.min.js"></script>
</head>
<body>
<div>
    <input type="text" name="money" placeholder="支付金额" style="width: 300px;height: 80px;">
    <p>
        <span style="cursor: pointer;" class="buybtnjs">支付</span>
    </p>
</div>

<div id="alipay">
</div>
</body>
<script src="https://res.wx.qq.com/open/js/jweixin-1.0.0.js"></script>
<script src="https://a.alipayobjects.com/g/h5-lib/alipayjsapi/3.0.3/alipayjsapi.inc.min.js"></script>
<script>
@if($type == 1)
    wx.config({
        debug: false,
        appId: '<?php  echo isset($signPackage["appId"])? $signPackage["appId"]:''  ?>',
        timestamp: '<?php  echo $signPackage["timestamp"]  ?>',
        nonceStr: '<?php   echo $signPackage["nonceStr"]  ?>',
        signature: '<?php  echo $signPackage["signature"]  ?>',
        jsApiList: [
            'chooseWXPay',
        ]
    });
@endif;
$(document).on("click",".buybtnjs",function(){
    var money = $(" input[ name='money']").val();
    var regPos = /^\d+(\.\d+)?$/; //非负浮点数
//    var regNeg = /^(-(([0-9]+\.[0-9]*[1-9][0-9]*)|([0-9]*[1-9][0-9]*\.[0-9]+)|([0-9]*[1-9][0-9]*)))$/; //负浮点数
    if(!regPos.test(money)){
        alert('价格不正确');
        return false;
    }
    if(money <= 0){
        alert('价格不正确');
        return false;
    }

    $.ajax({
        url : '/index/share',
        type : 'post',
        data : {'money':money},
        dataType : 'json',
        success : function(msg){
            if(msg.success == 1) {
                wx.chooseWXPay({
                    appId: msg.data.appId,
                    timestamp: msg.data.timestamp, // 支付签名时间戳，注意微信jssdk中的所有使用timestamp字段均为小写。但最新版的支付后台生成签名使用的timeStamp字段名需大写其中的S字符
                    nonceStr: msg.data.nonceStr, // 支付签名随机串，不长于 32 位
                    package: msg.data.package, // 统一支付接口返回的prepay_id参数值，提交格式如：prepay_id=***）
                    signType: msg.data.signType, // 签名方式，默认为'SHA1'，使用新版支付需传入'MD5'
                    paySign: msg.data.paySign, // 支付签名
                    success: function (res) {
                        var str = JSON.stringify(res);
                        // 支付成功后的回调函数
                        if (res.errMsg == "chooseWXPay:ok") {
                            alert('支付成功');
                        } else {
                            alert(msg.error);
                        }
                    },
                    cancel: function () {
                        alert('取消支付');
                    }
                });
            }else if(msg.success == 1688)//支付宝
            {
                $("#alipay").html(msg.data);
            }else{
                alert(msg.error);
            }
        },
        error : function(msg){
            console.log('no');
        }
    });
});
</script>
</html>