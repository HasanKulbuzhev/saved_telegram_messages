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
    private API $MadelineProto;
    private false|array|string $channelPeer;
    private $me;
    private string $messages_file_name;
    private string|int|null $fromPeer;
    private CacheMessageService $cacheHelper;

    public function __construct(string $channelPeer, string|int|null $fromPeer = null)
    {
        $this->MadelineProto = new API('session.madeline');
        $this->MadelineProto->async(false);
        $this->MadelineProto->start();

        $this->channelPeer = $channelPeer;
        $this->me = $this->MadelineProto->getSelf();
        $this->fromPeer = $fromPeer;
        if (is_null($this->fromPeer)) {
            $this->fromPeer = $this->me['id'];
        }
        $this->messages_file_name = 'messages' . $channelPeer . '_' . $fromPeer;
        $this->cacheHelper = new CacheMessageService('messages' . $channelPeer . '_' . $fromPeer, $this->fromPeer, $this->channelPeer);
    }

    public function getMessages(): array
    {
        $this->cacheMessages();

//        return $this->cacheHelper->getData();
        return [];
    }

    private function cacheMessages()
    {
        do {
            if (isset($offset_id)) {
                $messages = $this->MadelineProto->messages->getHistory(['offset_id' => $offset_id, 'peer' => $this->fromPeer, 'limit' => 30])['messages'];
            } else {
                $messages = $this->MadelineProto->messages->getHistory(['peer' => $this->fromPeer, 'limit' => 30])['messages'];
            }

            $messageIds = [];
            $offset_id = null;

            foreach ($messages as $message) {
                if (is_null($offset_id)) {
                    $offset_id = $message['id'];
                }

                if ($this->cacheHelper->issetMessageToId($message['id'])) {
                    continue;
                }

                $groupedId = $message['grouped_id'] ?? null;

                $this->cacheHelper->saveMessage(
                    $this->fromPeer,
                    $this->channelPeer,
                    $message['id'],
                    null,
                    $groupedId,
                    false,
                    !isset($message['fwd_from'])
                );

                $messageIds[] = $message['id'];
                echo "Сообщение с id=" . $message['id'] . " сохранено в массив! \n";

                continue;
            }

            if (!empty($messageIds)) {
//                $this->cacheHelper->updateValues(null, ['ids' => $messageIds, 'send' => false]);
            }
        } while (count($messageIds) > 0);

        echo "Идентификаторы сообщений успешно сохранены в файл! \n";
    }

    public function forwardAllMessages()
    {
        $this->cacheMessages();
        $messages = $this->cacheHelper->getAllNotSendMessages();
        foreach ($messages as $message) {
            $message = $this->cacheHelper->getDataToId($this->fromPeer, $this->channelPeer, $message['id']);
            
            if ($message['send'] == true) {
                echo "Сообщение с id " . $message['from_message_id'] . " уже было переслано! \n";
                continue;
            }

            // для групповых
            if (!is_null($message['group_id'])) {
                $groupMessageIds = [];
                $groupMessages = [];
                $groupMessageQuery = $this->cacheHelper->getDataToGroupId($this->fromPeer, $this->channelPeer, $message['group_id']);

                while ($groupMessage = $groupMessageQuery->fetchArray()) {
                    $groupMessages[] = $groupMessage;
                    $groupMessageIds[] = $groupMessage['from_message_id'];
                }

                $isSend = $this->MadelineProto->messages->forwardMessages(['id' => $groupMessageIds, 'from_peer' => $this->fromPeer, 'to_peer' => $this->channelPeer]);
                unset($groupMessageQuery);

                $newMessage = $this->MadelineProto->messages->getHistory(['peer' => $this->fromPeer, 'limit' => 1])['messages'][0];

                if ($isSend) {
                    foreach ($groupMessages as $groupMessage) {
                        $this->cacheHelper->updateMessage($groupMessage['id'], 'send', true);
                        $this->cacheHelper->updateMessage($groupMessage['id'], 'to_message_id', $newMessage['id']);
                    }
                    echo "Сообщения с id " . $message['from_message_id'] . " пересланы! \n";
                }

                continue;
            }

            $isSend = $this->MadelineProto->messages->forwardMessages(['id' => [$message['from_message_id']], 'from_peer' => $this->fromPeer, 'to_peer' => $this->channelPeer]);
            $newMessage = $this->MadelineProto->messages->getHistory(['peer' => $this->channelPeer, 'limit' => 1])['messages'][0];

            if ($isSend) {
                $this->cacheHelper->updateMessage($message['id'], 'send', 1);
                $this->cacheHelper->updateMessage($message['id'], 'to_message_id', $newMessage['id']);
                echo "Сообщения с id " . $message['from_message_id'] . " пересланы! \n";
            } else {
                echo "Сообщения с id " . $message['from_message_id'] . " не были пересланы! \n";
            }

            sleep(1);
        }
    }

    public function deleteNotActualMessage()
    {
        $fromMessageIds = ChannelHelper::getChannelMessageIds($this->MadelineProto, $this->fromPeer);

        foreach ($this->cacheHelper->getAllSendMessages() as $message) {
            if (!in_array($message['from_message_id'], $fromMessageIds)) {
                $this->MadelineProto->channels->deleteMessages([
                    'channel' => $this->channelPeer,
                    'id' => [$message['to_message_id']]
                ]);
            }
            echo "Сообщение с id={$message['to_message_id']} Успешно Удалено! \n";
        }
    }

    #[NoReturn] public function sync()
    {
        $this->forwardAllMessages();
        $this->deleteNotActualMessage();
    }
}