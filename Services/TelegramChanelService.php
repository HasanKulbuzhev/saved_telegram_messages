<?php

namespace Services;

use danog\MadelineProto\API;

class TelegramChanelService
{
    private API $MadelineProto;
    public array $allForwardMessage = [];
    private false|array|string $channel_peer;
    private $me;
    private string $messages_file_name;

    public function __construct(string $channel_peer)
    {
        $this->MadelineProto = new API('session.madeline');
        $this->MadelineProto->async(false);
        $this->MadelineProto->start();

        $this->channel_peer = $channel_peer;
        $this->messages_file_name = 'messages' . $channel_peer;
        $this->me = $this->MadelineProto->getSelf();
    }

    public function getMessages(): array
    {
        if (file_exists($this->messages_file_name)) {
            $this->allForwardMessage = json_decode(file_get_contents($this->messages_file_name), true);
        } else {
            $this->setMessages();
        }

        return $this->allForwardMessage;
    }

    private function setMessages()
    {
        $messages = $this->MadelineProto->messages->getHistory(['peer' => $this->me['id'], 'limit' => 30])['messages'];
        do {
            if (isset($offset_id)) {
                $messages = $this->MadelineProto->messages->getHistory(['offset_id' => $offset_id, 'peer' => $this->me['id'], 'limit' => 30])['messages'];
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
                $this->allForwardMessage[] = ['ids' => $messageIds, 'send' => false];
            }
        } while (count($messages) > 0);

        if (file_put_contents($this->messages_file_name, json_encode($this->allForwardMessage))) {
            echo "Идентификаторы сообщений успешно сохранены в файл!";
        }
    }

    public function updateLocalMessage(int|string $key, array $updateMessage): bool|int
    {
        $newForwardMessages = json_decode(file_get_contents($this->messages_file_name), true);
        $newForwardMessages[$key] = $updateMessage;
        return file_put_contents($this->messages_file_name, json_encode($newForwardMessages));
    }

    public function allForwardMessage()
    {
        foreach (array_reverse($this->getMessages(), true) as $key => $forwardMessages) {
            if ($forwardMessages['send'] == true) {
                echo "Сообщения с id " . implode(', ', $forwardMessages['ids']) . " уже были пересланы! \n";
                continue;
            }

            $isSend = $this->MadelineProto->messages->forwardMessages(['id' => $forwardMessages['ids'], 'from_peer' => $this->me['id'], 'to_peer' => $this->channel_peer]);
            if ($isSend) {
                $forwardMessages['send'] = true;
                $this->updateLocalMessage($key, $forwardMessages);
                echo "Сообщения с id " . implode(', ', $forwardMessages['ids']) . " переслано! \n";
            } else {
                echo "Сообщения с id " . implode(', ', $forwardMessages['ids']) . " не были пересланы! \n";
            }

            sleep(2);
        }
    }
}