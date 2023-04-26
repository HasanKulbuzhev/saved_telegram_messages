<?php

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;
use Services\TelegramChanelService;

(DotEnv::createUnsafeImmutable(__DIR__))->load();

$channel_peer = getenv('CHANNEL_TO_USERNAME');
$syncDelete = getenv('SYNC_DELETE');
if (is_null($channel_peer)) {
    echo "Введите CHANNEL_USERNAME в .env";
    exit();
}

$service = new TelegramChanelService($channel_peer, getenv('CHANNEL_USERNAME'), 0, $syncDelete);
do {
    $service->sync();
} while (true);

echo "Все сообщения успешно сохранены \n";

