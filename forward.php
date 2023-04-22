<?php

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;
use Services\TelegramChanelService;

(DotEnv::createUnsafeImmutable(__DIR__))->load();

$channel_peer = getenv('CHANNEL_TO_USERNAME');
if (is_null($channel_peer)) {
    echo "Введите CHANNEL_USERNAME в .env";
    exit();
}

$service = new TelegramChanelService($channel_peer, getenv('CHANNEL_USERNAME'));
$service->sync();

echo "Все сообщения успешно сохранены \n";

