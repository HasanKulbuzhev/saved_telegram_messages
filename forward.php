<?php

use Services\TelegramChanelService;

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';
include 'DevCoder/DotEnv.php';

(new DevCoder\DotEnv(__DIR__ . '/.env'))->load();

$channel_peer = getenv('CHANNEL_TO_USERNAME');
if (is_null($channel_peer)) {
    echo "Введите CHANNEL_USERNAME в .env";
    exit();
}

$service = new TelegramChanelService($channel_peer, getenv('CHANNEL_USERNAME'));

$service->forwardAllMessages();

echo "Все сообщения успешно сохранены";

