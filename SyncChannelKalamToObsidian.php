<?php

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;
use Services\TelegramChanelService;

$service = new TelegramChanelService('@ilmu_al_kalam_official', getenv('CHANNEL_USERNAME'));
$service->saveMessagesKalamToObsidian();

echo "Все сообщения успешно сохранены \n";

