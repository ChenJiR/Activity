<?php

switch ($argv[0]) {
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
    return;
}

function getLotteryResult()
{
    return;
}