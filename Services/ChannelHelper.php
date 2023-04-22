<?php

namespace Services;

use danog\MadelineProto\API;

class ChannelHelper
{
    public static function getChannelMessageIds(API $api, string $channelPeer): array
    {
        $messageIds = [];
        do {
            if (isset($offset_id)) {
                $messages = $api->messages->getHistory(['offset_id' => $offset_id, 'peer' => $channelPeer])['messages'];
            } else {
                $messages = $api->messages->getHistory(['peer' => $channelPeer])['messages'];
            }

            $offset_id = null;

            foreach (array_reverse($messages) as $message) {
                if (is_null($offset_id)) {
                    $offset_id = $message['id'];
                }

                $messageIds[] = $message['id'];
            }
        } while (count($messages) > 0);

        return $messageIds;
    }
}