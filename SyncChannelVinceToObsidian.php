<?php

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;
use Services\TelegramChanelService;

$service = new TelegramChanelService('-1001415982160', getenv('CHANNEL_USERNAME'));
$service->saveMessagesToObsidian();

echo "Все сообщения успешно сохранены \n";

