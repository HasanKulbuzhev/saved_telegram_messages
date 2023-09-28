<?php

namespace Services;

use danog\MadelineProto\API;
use function Amp\File\deleteDirectory;
use function Amp\Iterator\concat;

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
        $this->cacheHelper = new CacheMessageService('messages' . $channelPeer . '_' . $fromPeer);
    }

    public function getMessages(): array
    {
        $this->cacheMessages();

        return $this->cacheHelper->getData();
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
            /** нужно для того, чтобы пропускать последние группированные сообщения */
            $is_grouped = true;
            $offset_id = null;

            foreach (array_reverse($messages) as $message) {
                if (!isset($messages['grouped_id'])) {
                    $is_grouped = false;
                }
                if ($is_grouped || ($message['id'] <= $this->cacheHelper->getLastMessageId())) {
                    continue;
                }

                if (is_null($offset_id)) {
                    $offset_id = $message['id'];
                }

                $messageIds[] = $message['id'];
                echo "Сообщение с id=" . $message['id'] . " сохранено в массив! \n";
            }

            // Если все сообщения в одной группе
            if ($is_grouped) {
                foreach (array_reverse($messages) as $message) {
                    if ($message['id'] <= $this->cacheHelper->getLastMessageId()) {
                        continue;
                    }

                    if (is_null($offset_id)) {
                        $offset_id = $message['id'];
                    }

                    $messageIds[] = $message['id'];
                    echo "Сообщение с id=" . $message['id'] . " сохранено в массив! \n";
                }

            }

            if (!empty($messageIds)) {
                $this->cacheHelper->updateValues(null, ['ids' => $messageIds, 'send' => false]);
            }
        } while (count($messageIds) > 0);

        echo "Идентификаторы сообщений успешно сохранены в файл! \n";
    }

    public function forwardAllMessages()
    {
        foreach (array_reverse($this->getMessages(), true) as $key => $forwardMessages) {
            if ($forwardMessages['send'] == true) {
                echo "Сообщения с id " . implode(', ', $forwardMessages['ids']) . " уже были пересланы! \n";
                continue;
            }

            $isSend = $this->MadelineProto->messages->forwardMessages(['id' => $forwardMessages['ids'], 'from_peer' => $this->fromPeer, 'to_peer' => $this->channelPeer]);
            if ($isSend) {
                $this->cacheHelper->updateValues($key, array_merge($forwardMessages, ['send' => true]));
                echo "Сообщения с id " . implode(', ', $forwardMessages['ids']) . " пересланы! \n";
            } else {
                echo "Сообщения с id " . implode(', ', $forwardMessages['ids']) . " не были пересланы! \n";
            }

            sleep(2);
        }
    }

    /**
     * Сохраняет публикацию в обсидиан
     */
    public function saveMessagesToObsidian()
    {
        do {
            if (isset($offset_id)) {
                $messages = $this->MadelineProto->messages->getHistory(['offset_id' => $offset_id, 'peer' => $this->channelPeer, 'limit' => 30])['messages'];
            } else {
                $messages = $this->MadelineProto->messages->getHistory(['peer' => $this->channelPeer, 'limit' => 30])['messages'];
            }

            $messageIds = [];
            $offset_id = null;

            foreach (array_reverse($messages) as $message) {
                if (is_null($offset_id)) {
                    $offset_id = $message['id'];
                }

                if (!isset($message['message'])) {
                    continue;
                }

                $text = '
202209212208
Tags: #discord #linux #video #striming
__
# Мысль "' . substr($message['message'], 0, 100) . $message['id'] . '"
' . '
```text
' . $message['message'] . '
```
__
### Zero-Links
[[Мысли]]
[[Продуктивность]]
[[Пристанище речей]]

__ 
### Links
https://t.me/channel_vince/' . $message['id'] . '
';

                file_put_contents(
                    '/home/hasan/tmp/Мысль "' . $message['id'] . '".md',
                    $text
                );

                $messageIds[] = $message['id'];
                echo "мысль " . '"' . substr(
                        $message['message'],
                        0,
                        100
                    ) . '" ' . " сохранена! \n";

            }
        } while (count($messageIds) > 0);

        echo "Идентификаторы сообщений успешно сохранены в файл! \n";
    }

    /**
     * Сохраняет публикацию в обсидиан
     */
    public function saveMessagesKalamToObsidian()
    {
        $path = '/home/hasan/Документы/IlmuKalamObsidian/';
        do {
            if (isset($offset_id)) {
                $messages = $this->MadelineProto->messages->getHistory(['offset_id' => $offset_id, 'peer' => $this->channelPeer, 'limit' => 30])['messages'];
            } else {
                $messages = $this->MadelineProto->messages->getHistory(['peer' => $this->channelPeer, 'limit' => 30])['messages'];
            }

            $messageIds = [];
            $offset_id = null;

            foreach (array_reverse($messages) as $message) {
                if (is_null($offset_id)) {
                    $offset_id = $message['id'];
                }

                if (!isset($message['message'])) {
                    continue;
                }

                if (empty($message['message'])) {
                    continue;
                }


                $booksToString = '';
//                $books = explode('separator', str_replace(['[', ']'], 'separator', '[test] test1 [test2  ] test 3'));
                $books = explode('separator', str_replace(['[', ']'], 'separator', $message['message']));
                foreach ($books as $key => $book) {
                    if ($key % 2 === 1) {
                        $bookFileName = "Книга ". str_replace(['/', '\\', '\\/'], "_", $book);
                        $bookFileName = substr($bookFileName, 0, 130);
                        if (!file_exists($path . "Книга ". str_replace(['/', '\\', '\\/'], "_", $book) . '.md')) {
                            $textBook = "
#book 
$book

### Zero-Links
[[Ильму Аль Калям]]

                                 ";
                            file_put_contents(
                                $path . $bookFileName . '.md',
                                $textBook);
                        }
                        $booksToString .= "\n [[Книга $book]]";
                    }
                }

                $text = '
202209212208
Tags: #Калям #ИльмуКалям #Kalam #post
__
# Мысль "' . str_replace("\n", "", substr($message['message'], 0, 100)) . '" ' . $message['id'] . '
' . '
```text
' . $message['message'] . "
```
__
### Zero-Links
[[Ильму Аль Калям]]
$booksToString

__ 
### Links
[Ссылка на Ильму Аль Калям](https://t.me/ilmu_al_kalam_official/" . $message['id'] . ')
';

                file_put_contents(
                    $path . 'Пост "' . $message['id'] . '".md',
                    $text
                );

                $messageIds[] = $message['id'];
                echo "мысль " . '"' . substr(
                        $message['message'],
                        0,
                        100
                    ) . '" ' . " сохранена! \n";

            }
        } while (count($messageIds) > 0);

        echo "Идентификаторы сообщений успешно сохранены в файл! \n";
    }
}
