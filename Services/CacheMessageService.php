<?php

namespace Services;

use Exception;
use JetBrains\PhpStorm\Pure;
use SQLite3;

class CacheMessageService
{
    private string $file_name;
    private string $fromPeer;
    private string $channelPeer;

    public function __construct(string $fileName, string $fromPeer, string $channelPeer)
    {
        $this->file_name = $fileName;
        $this->fill();
        $this->fromPeer = $fromPeer;
        $this->channelPeer = $channelPeer;
    }

    private function fill()
    {
//        $this->messages = $this->getData(true);
    }

    public function getQuery(): SQLite3
    {
        return new SQLite3('./saved_message_db');
    }

    public function saveMessage(string $fromPeer, string $toPeer, int $fromMessageId, ?int $toMessageId = null, ?int $groupId = null, int $send = 0, bool $owner = false)
    {
        $send = (int)$send;
        $prepare = $this->getQuery()->prepare('insert into history (from_channel_id, to_channel_id, from_message_id, to_message_id, group_id, send, owner) values (?, ?, ?, ?, ?, ?, ?)');
        $prepare->bindParam(1, $fromPeer);
        $prepare->bindParam(2, $toPeer);
        $prepare->bindParam(3, $fromMessageId);
        $prepare->bindParam(4, $toMessageId);
        $prepare->bindParam(5, $groupId);
        $prepare->bindParam(6, $send);
        $prepare->bindParam(7, $owner);
        $prepare->execute();
    }

    public function updateMessage(int $id, string $column, $value)
    {
        $prepare = $this->getQuery()->prepare(sprintf('update history set %s=? where id=?', $column));

        $prepare->bindParam(1, $value);
        $prepare->bindParam(2, $id);

        $prepare->execute();
    }

    public function deleteMessage(int $id)
    {
        $prepare = $this->getQuery()->prepare('delete from history where id=?');

        $prepare->bindParam(1, $id);

        $prepare->execute();
    }

    #[Pure] public function issetFile(): bool
    {
        return file_exists($this->file_name);
    }

    public function getData(string $fromChannelPeer, string $toChannelPeer, bool $desk = false, bool $send = false, $limit = null): \SQLite3Result
    {
        $orderType = $desk ? 'DESC' : 'ASC';
        $sql = sprintf('Select * from history where from_channel_id=\'%s\' and to_channel_id=\'%s\' and send=%s order by from_message_id %s %s', $fromChannelPeer, $toChannelPeer, (int)$send, $orderType, is_null($limit) ? '' : "LIMIT $limit");
        $query = $this->getQuery();
        return $query->query($sql);
    }

    public function getAllNotSendMessages($fromPeer, $toPeer): array
    {
        $query = $this->getData($fromPeer, $toPeer);
        $messages = [];
        while ($localMessage = $query->fetchArray()) {
            $messages[] = $localMessage;
        }

        return $messages;
    }

    public function getAllSendMessages($fromPeer, $toPeer, $limit = null): array
    {
        $query = $this->getData($fromPeer, $toPeer, true, true, $limit);
        $messages = [];
        while ($localMessage = $query->fetchArray()) {
            $messages[] = $localMessage;
        }

        return $messages;
    }

    public function getDataToId(string $fromChannelPeer, string $toChannelPeer, int $id)
    {
        $sql = sprintf('Select * from history where from_channel_id=\'%s\' and to_channel_id=\'%s\' and id=\'%s\' order by from_message_id ASC', $fromChannelPeer, $toChannelPeer, $id);
        $query = $this->getQuery();
        return $query->query($sql)->fetchArray();
    }

    public function getDataToGroupId(string $fromChannelPeer, string $toChannelPeer, int $groupId): \SQLite3Result
    {
        $sql = sprintf('Select * from history where from_channel_id=\'%s\' and to_channel_id=\'%s\' and group_id=\'%s\' order by from_message_id ASC', $fromChannelPeer, $toChannelPeer, $groupId);
        $query = $this->getQuery();
        return $query->query($sql);
    }

    public function issetMessageFromId($fromPeer, $toPeer, $fromMessageId): bool
    {
        $sql = sprintf('select * from history where from_channel_id=%s and to_channel_id=%s and from_message_id=%s', $fromPeer, $toPeer, $fromMessageId);
        return (bool)$this->getQuery()->querySingle($sql);
    }

    public function issetMessageToId($fromPeer, $toPeer, $fromMessageId): bool
    {
        $sql = sprintf('select * from history where from_channel_id=%s and to_channel_id=%s and to_message_id=%s', $fromPeer, $toPeer, $fromMessageId);
        return (bool)$this->getQuery()->querySingle($sql);
    }

//    public function updateValues($key, array $data): bool|int
//    {
//        $newData = $this->getData();
//
//        if (is_null($key)) {
//            $newData[] = $data;
//        } else {
//            $newData[$key] = $data;
//        }
//
//        return $this->setData($newData);
//    }

//    public function setData(array $data): bool|int
//    {
//        try {
//            return file_put_contents($this->file_name, json_encode($data));
//        } catch (Exception $exception) {
//            throw $exception;
//        } finally {
//            $this->fill();
//        }
//    }

    public function getLastMessageId()
    {
        return $this->getQuery()->querySingle(sprintf('select max(from_channel_id) from history where from_channel_id=%s and to_channel_id=%s', $this->fromPeer, $this->channelPeer));
    }
}