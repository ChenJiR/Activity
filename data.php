<?php

require "model.php";
require "service.php";
require "helper.php";

switch ($argv[1]) {
    case "user":
        getUserInfo();
        break;
    case "lottery":
        getLotteryResult();
        break;
    default:
        echo "请输入 user 或 lottery 选择您想要查看的内容";
        exit;
}


function getUserInfo()
{
    $file = fopen("user.txt", "w");
    fwrite($file, "   手机号       征文内容         报名时间 " . PHP_EOL);
    foreach (RedisService::getInstance()->iterratorKeys("u:*") as $u) {
        $user = unserialize($u);
        fwrite($file, "$user->phone    $user->text    $user->sign_up_time " . PHP_EOL);
    }
    fclose($file);
    exit("generate file success");
}

function getLotteryResult()
{
    $prize_json = file_get_contents("prize.json");
    $prize_list = [];
    foreach (json_decode($prize_json, true) as $item) {
        $prize_list[$item["code"]] = $item["name"];
    }
    $file = fopen("lottery.txt", "w");
    fwrite($file, "   手机号       奖品         中奖时间 " . PHP_EOL);
    foreach (RedisService::getInstance()->iterratorKeys("lottery:*") as $l) {
        $lottery = unserialize($l);
        fwrite($file, "$lottery->phone    " . $prize_list[$lottery->prize_code] . "    $lottery->lottery_time " . PHP_EOL);
    }
    fclose($file);
    exit("generate file success");
}