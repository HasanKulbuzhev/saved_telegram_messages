<?php

namespace Services;

use danog\MadelineProto\API;
use JetBrains\PhpStorm\NoReturn;
use Ufee\Sqlite3\Sqlite;

/**
 * Class TelegramChanelService
 * @package Services
 *
 *         to - id 1, 2, 3, 4, 5, 6, ...,
 *         do - id 1, 2, 3, 4, 5, 6
 *
 */
class TelegramChanelService
{
    private API                 $MadelineProto;
    private false|array|string  $channelPeer;
    private                     $me;
    private string|int|null     $fromPeer;
    private CacheMessageService $cacheHelper;
    private int                 $fromMessageId;
    private bool                $syncDelete;

    public function __construct(string $channelPeer, string|int|null $fromPeer = null, int $fromMessageId = 0, bool $syncDelete = false)
    {
        $this->MadelineProto = new API('session.madeline');
        $this->MadelineProto->async(false);
        $this->MadelineProto->start();

        $this->fromMessageId = $fromMessageId;
        $this->channelPeer = $channelPeer;
        $this->me = $this->MadelineProto->getSelf();
        $this->fromPeer = $fromPeer;
        if (is_null($this->fromPeer)) {
            $this->fromPeer = $this->me['id'];
        }
        $this->cacheHelper = new CacheMessageService('messages' . $channelPeer . '_' . $fromPeer, $this->fromPeer, $this->channelPeer);
        $this->syncDelete = (bool) $syncDelete;
    }

    private function cacheMessages(string $fromChannel, string $toChannel)
    {
        do {
            if (isset($offset_id)) {
                $messages = $this->MadelineProto->messages->getHistory(['offset_id' => $offset_id, 'peer' => $fromChannel])['messages'];
            } else {
                $messages = $this->MadelineProto->messages->getHistory(['peer' => $fromChannel])['messages'];
            }

            $messageIds = [];
            $offset_id = null;

            foreach ($messages as $message) {
                if (is_null($offset_id)) {
                    $offset_id = $message['id'];
                }

                if ($this->cacheHelper->issetMessageFromId($fromChannel, $toChannel, $message['id']) ||
                    $this->cacheHelper->issetMessageToId($toChannel, $fromChannel, $message['id'])) {
                    continue;
                }

                $groupedId = $message['grouped_id'] ?? null;

                $this->cacheHelper->saveMessage(
                    $fromChannel,
                    $toChannel,
                    $message['id'],
                    null,
                    $groupedId,
                    0,
                    !isset($message['fwd_from'])
                );

                $messageIds[] = $message['id'];
                echo "Сообщение с id=" . $message['id'] . " сохранено в массив! \n";

                continue;
            }

            if ($messages[0]['id'] < $this->fromMessageId) {
                break;
            }

        } while (count($messageIds) > 0);

        echo "Идентификаторы сообщений успешно сохранены в файл! \n";
    }

    public function forwardAllMessages($fromPeer, $toPeer)
    {
        $this->cacheMessages($fromPeer, $toPeer);
        $messages = $this->cacheHelper->getAllNotSendMessages($fromPeer, $toPeer);
        foreach ($messages as $message) {
            if ($message['from_message_id'] < $this->fromMessageId || $message['from_message_id'] < 2) {
                continue;
            }
            $message = $this->cacheHelper->getDataToId($fromPeer, $toPeer, $message['id']);
            $this->sendMessage($message);

            if ($message['send'] == true) {
                echo "Сообщение с id " . $message['from_message_id'] . " уже было переслано! \n";
                continue;
            }

            // для групповых
            if (!is_null($message['group_id'])) {
                $groupMessageIds = [];
                $groupMessages = [];
                $groupMessageQuery = $this->cacheHelper->getDataToGroupId($fromPeer, $toPeer, $message['group_id']);

                while ($groupMessage = $groupMessageQuery->fetchArray()) {
                    $groupMessages[] = $groupMessage;
                    $groupMessageIds[] = $groupMessage['from_message_id'];
                }

                $isSend = $this->MadelineProto->messages->forwardMessages(['id' => $groupMessageIds, 'from_peer' => $fromPeer, 'to_peer' => $toPeer]);
                unset($groupMessageQuery);

                if ($isSend) {
                    foreach ($groupMessages as $key => $groupMessage) {
                        $newMessage = array_reverse($this->MadelineProto->messages->getHistory(['peer' => $toPeer, 'limit' => count($groupMessages)])['messages']);

                        $this->cacheHelper->updateMessage($groupMessage['id'], 'send', 1);
                        $this->cacheHelper->updateMessage($groupMessage['id'], 'to_message_id', $newMessage[$key]['id']);
                    }
                    echo "Сообщения https://t.me/c/{$fromPeer}/{$message['from_message_id']} " . " переслано! \n";
                }

                continue;
            }

            try {
                $isSend = $this->MadelineProto->messages->forwardMessages(['id' => [$message['from_message_id']], 'from_peer' => $fromPeer, 'to_peer' => $toPeer]);
            } catch (\Exception $e) {
                if ($e->getMessage() === "MESSAGE_ID_INVALID") {
                    $this->cacheHelper->deleteMessage($message['id']);
                    continue;
                }

                throw $e;
            }
            $newMessage = $this->MadelineProto->messages->getHistory(['peer' => $toPeer, 'limit' => 1])['messages'][0];

            if ($isSend) {
                $this->cacheHelper->updateMessage($message['id'], 'send', 1);
                $this->cacheHelper->updateMessage($message['id'], 'to_message_id', $newMessage['id']);
                echo "Сообщения https://t.me/c/{$fromPeer}/{$message['from_message_id']} " . " переслано! \n";
            } else {
                echo "Сообщения https://t.me/c/{$fromPeer}/{$message['from_message_id']} " . "не было переслано! \n";
            }

            sleep(1);
        }
    }

    public function deleteNotActualMessage($fromPeer, $toPeer)
    {
        $fromMessageIds = ChannelHelper::getChannelMessageIds($this->MadelineProto, $fromPeer, 100);
        if ($this->syncDelete) {
            $toMessageIds = ChannelHelper::getChannelMessageIds($this->MadelineProto, $toPeer, 300);
        }

        foreach ($this->cacheHelper->getAllSendMessages($fromPeer, $toPeer, 100) as $message) {
            if (!in_array($message['from_message_id'], $fromMessageIds)) {
                $this->MadelineProto->channels->deleteMessages([
                    'channel' => $toPeer,
                    'id' => [$message['to_message_id']]
                ]);
                $this->cacheHelper->updateMessage($message['id'], 'send', 2);

                echo "Сообщение https://t.me/c/{$toPeer}/{$message['to_message_id']} Успешно Удалено! \n";
            }


            if ($this->syncDelete) {
                if (!in_array($message['to_message_id'], $toMessageIds)) {
                    $this->MadelineProto->channels->deleteMessages([
                        'channel' => $fromPeer,
                        'id' => [$message['from_message_id']]
                    ]);
                    $this->cacheHelper->updateMessage($message['id'], 'send', 2);

                    echo "Сообщение https://t.me/c/{$fromPeer}/{$message['from_message_id']} Успешно Удалено! \n";
                }
            }
        }
    }

    #[NoReturn] public function sync()
    {
        $this->forwardAllMessages($this->fromPeer, $this->channelPeer);
        $this->deleteNotActualMessage($this->fromPeer, $this->channelPeer);

        $this->forwardAllMessages($this->channelPeer, $this->fromPeer);
        $this->deleteNotActualMessage($this->channelPeer, $this->fromPeer);
    }

    private function sendMessage(array $message)
    {

    }
}