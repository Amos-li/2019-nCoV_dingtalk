<?php

/**
 * @Author: yumu
 * @Date:   2020-01-26
 * @Email:   yumusb@foxmail.com
 * @Last Modified by:   yumu
 * @Last Modified time: 2020-01-27
 */
//----------------
//钉钉hooktoken
$token = "上步骤webhook地址后面的token字段";
//临时文件的文件名
$localfilename = "tmp.html";
//页面地址
$pageurl = "你的文件访问页面地址";
//推送地区,如果仅需要省份形式，就例如北京市。需要市级信息，写成数组形式
$jiankong = array('北京市', '山东省' => array("青岛", "临沂", "菏泽"));
//设置的关键字
$keyword="你设置的触发关键字";
//------------------------
date_default_timezone_set("PRC");
if (date("H") >= 23 || date("H") <= 8) {
    exit("auto push service down"); //夜间不推送
}
function curl_get($url)
{
    $ch = curl_init();
    $defaultOptions = [
        CURLOPT_URL => $url,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language:zh-CN,en-US;q=0.7,en;q=0.3",
            "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.99 Safari/537.36",
        ]
    ];
    curl_setopt_array($ch, $defaultOptions);
    $chContents = curl_exec($ch);
    $curlInfo = curl_getinfo($ch);

    curl_close($ch);

    if ($curlInfo['http_code'] != 200) {
        $contents = $curlInfo['http_code'];
    } else {
        $contents = $chContents;
    }

    return $contents;
}

function request_by_curl($remote_server, $post_string)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_server);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json;charset=utf-8'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // 线下环境不用开启curl证书验证, 未调通情况可尝试添加该代码
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0); 
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}
$webhook = "https://oapi.dingtalk.com/robot/send?access_token=" . $token;
$largeimg = json_decode(curl_get("https://i.snssdk.com/forum/home/v1/info/?forum_id=1656388947394568&is_web_refresh=1"), true)['forum']['extra']['ncov_image_url']; //拿头条的大图
$html = curl_get("https://3g.dxy.cn/newh5/view/pneumonia");
$StatisticsService = explode("}catch(e)", explode("window.getStatisticsService = ", $html)[1])[0];
if ($StatisticsService == "") {
    exit("page return error"); //丁香园抽疯，没有返回完整页面
}
$local = file_get_contents($localfilename);
if ($StatisticsService === $local) {
    exit("no new message"); //没有更新
}
unlink($localfilename);
file_put_contents($localfilename, $StatisticsService);

$StatisticsService = json_decode($StatisticsService, true);
$area = explode("}catch(e)", explode("window.getAreaStat = ", $html)[1])[0];
$area = json_decode($area, true);
foreach ($area as $shengfen) {
    if (in_array($shengfen['provinceName'], $jiankong) || $jiankong[$shengfen['provinceName']] != null) {
        $tmpmessage .="    \n".$shengfen['provinceName'] . " 确诊 " . $shengfen['confirmedCount'] . " 治愈 " . $shengfen['curedCount'] . " 死亡 " . $shengfen['deadCount'] . "  \n"; //拿到省级数据
        if (is_array($jiankong[$shengfen['provinceName']])) {
            foreach ($shengfen['cities'] as $chengshi) {
                if (in_array($chengshi['cityName'],$jiankong[$shengfen['provinceName']])) {
                    $tmpmessage .= '> ' . $chengshi['cityName'] . " 确诊 " . $chengshi['confirmedCount'] . " 治愈 " . $chengshi['curedCount'] . " 死亡 " . $chengshi['deadCount'] . "  \n"; //再加省下级具体市数据数据
                }
            }
        }
    }
}
$message = date("Y-m-d H:i:s", substr($StatisticsService['modifyTime'], 0, 10)) . " 更新  现在时间" . date("Y-m-d H:i:s") . "\n ![]({$largeimg})  \n确诊 {$StatisticsService['confirmedCount']}  疑似 {$StatisticsService['suspectedCount']} 治愈 {$StatisticsService['curedCount']} 死亡 {$StatisticsService['deadCount']}  \n {$tmpmessage} \n五分钟自动检测更新情况 手动检测请点击[此处]({$pageurl})   \n丁香园[原文](https://3g.dxy.cn/newh5/view/pneumonia)   \nby:{$keyword}";
//$message = date("Y-m-d H:i:s", substr($StatisticsService['modifyTime'], 0, 10)) . " 更新  现在时间" . date("Y-m-d H:i:s") . "\n ![]({$largeimg})  \n" . $StatisticsService['countRemark'] . "  \n {$tmpmessage}    \n五分钟自动检测更新情况 手动检测请点击[此处]({$pageurl})   \n丁香园[原文](https://3g.dxy.cn/newh5/view/pneumonia)   \nby:{$keyword}";
//echo $message;

$data = array('msgtype' => 'markdown', 'markdown' => array('title' => '疫情播报', 'text' => $message));
$data_string = json_encode($data);

$result = request_by_curl($webhook, $data_string);
echo $result;
