<?php

    // 该软件遵循MIT开源协议。
    // 编码
    header("Content-type:application/json");
    
    // 获取Access Token的函数
    function getAccessToken($corpid, $corpsecret)
    {
        $tokenFile = 'access_token.php';
    
        // 如果access_token文件存在且未过期，直接读取缓存的token
        if (file_exists($tokenFile) && time() - filemtime($tokenFile) < 7200) {
            return file_get_contents($tokenFile);
        }
    
        $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corpid}&corpsecret={$corpsecret}";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        $access_token = $data['access_token'];
    
        // 将access_token保存到文件中
        if ($access_token) {
            file_put_contents($tokenFile, $access_token);
        }
    
        return $access_token;
    }
    
    // 发送应用消息的函数
    function sendApplicationMessage($accessToken, $postData)
    {
        $url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={$accessToken}";
    
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/json",
                'method'  => 'POST',
                'content' => json_encode($postData),
            ),
        );
    
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return $result;
    }
    
    // 获取企业微信通知配置
    include '../Db.php';
    $db = new DB_API($config);
    $getQywxNotificationConfig = $db->set_table('huoma_notification')->find();
    
    if($getQywxNotificationConfig){
        
        // corpid
        $noti_corpid = json_decode(json_encode($getQywxNotificationConfig))->corpid;
        
        // corpsecret
        $noti_corpsecret = json_decode(json_encode($getQywxNotificationConfig))->corpsecret;
        
        // touser
        $noti_touser = json_decode(json_encode($getQywxNotificationConfig))->touser;
        
        // agentid
        $noti_agentid = json_decode(json_encode($getQywxNotificationConfig))->agentid;
    }
    
    // 请用您实际的企业微信信息替换以下变量
    $corpid = $noti_corpid;
    $corpsecret = $noti_corpsecret;
    $noti_text = $_GET["noti_text"];

    // Webhook 模式：corpid 字段填的是企业微信群机器人 Webhook 地址（http/https 开头）时，
    // 直接 POST 文本消息到群机器人，无需自建应用的 corpid/corpsecret/agentid/touser
    if (stripos($corpid, 'http') === 0) {

        $webhookPostdata = array(
            'msgtype' => 'text',
            'text' => array(
                'content' => $noti_text
            )
        );

        $webhookOptions = array(
            'http' => array(
                'header'  => "Content-type: application/json",
                'method'  => 'POST',
                'content' => json_encode($webhookPostdata, JSON_UNESCAPED_UNICODE),
                'timeout' => 5
            )
        );

        $webhookContext = stream_context_create($webhookOptions);
        $webhookResponse = file_get_contents($corpid, false, $webhookContext);
        echo $webhookResponse;
        exit;
    }

    // 要发送的应用消息数据
    $postdata = array(
        'touser' => $noti_touser, // 接收者的用户ID
        'msgtype' => 'text',
        'agentid' => $noti_agentid, // 应用的ID
        'text' => array(
            'content' => $noti_text
        )
    );
    
    // 获取Access Token
    $accessToken = getAccessToken($corpid, $corpsecret);
    
    // 发送应用消息
    $response = sendApplicationMessage($accessToken, $postdata);
    
    // 输出响应结果
    echo $response;
    
?>
