<?php


include 'madeline.php';
include 'vendor/autoload.php';
include 'DevCoder/DotEnv.php';

$bot = new \Telegram\Bot\Api('5244678975:AAG8jm3XLtfq6pfNo-aegYSdTunOeX1Qz6A');
$self = $bot->getMe();

var_dump($bot->getUpdates());
//var_dump($self);
exit();

