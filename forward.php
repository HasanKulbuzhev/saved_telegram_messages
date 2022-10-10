<?php

use danog\MadelineProto\API;

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';
include 'DevCoder/DotEnv.php';

(new DevCoder\DotEnv(__DIR__ . '/.env'))->load();

$channel_peer = getenv('CHANNEL_USERNAME');
if (is_null($channel_peer)) {
    echo "Введите CHANNEL_USERNAME в .env";
    exit();
}

$MadelineProto = new API('session.madeline');
$MadelineProto->async(false);
$MadelineProto->start();

$me = $MadelineProto->getSelf();

$messages = $MadelineProto->messages->getHistory(['peer' => $me['id'], 'limit' => 30])['messages'];
$allForwardMessage = [];

if (!file_exists('messages.json')) {
    do {
        if (isset($offset_id)) {
            $messages = $MadelineProto->messages->getHistory(['offset_id' => $offset_id, 'peer' => $me['id'], 'limit' => 30])['messages'];
        }

        $messageIds = [];
        /** нужно для того, чтобы пропускать последние группированные сообщения */
        $is_grouped = true;
        $offset_id = null;

        foreach (array_reverse($messages) as $message) {
            if (!isset($messages['grouped_id'])) {
                $is_grouped = false;
            }
            if ($is_grouped) {
                continue;
            }

            if (is_null($offset_id)) {
                $offset_id = $message['id'];
            }

            $messageIds[] = $message['id'];
            echo "Сообщение с id=" . $message['id'] . " сохранено в массив! \n";
        }

        if (!empty($messages)) {
            $allForwardMessage[] = ['ids' => $messageIds, 'send' => false];
        }
    } while (count($messages) > 0);

    if (file_put_contents('messages.json', json_encode($allForwardMessage))) {
        echo "Идентификаторы сообщений успешно сохранены в файл!";
    }
} else {
    $allForwardMessage = json_decode(file_get_contents('messages.json'), true);
}

foreach (array_reverse($allForwardMessage, true) as $key => $forwardMessages) {
    if ($forwardMessages['send'] == true) {
        continue;
    }

    $isSend = $MadelineProto->messages->forwardMessages(['id' => $forwardMessages['ids'], 'from_peer' => $me['id'], 'to_peer' => $channel_peer]);
    if ($isSend) {
        $newForwardMessages = json_decode(file_get_contents('messages.json'), true);
        $newForwardMessages[$key]['send'] = true;
        file_put_contents('messages.json', json_encode($newForwardMessages));
        echo "Сообщения с id " . implode(', ', $forwardMessages['ids']) . " переслано! \n";
    } else {
        echo "Сообщения с id " . implode(', ', $forwardMessages['ids']) . " не были пересланы! \n";
    }

    sleep(2);
}

echo "Все сообщения успешно сохранены";

