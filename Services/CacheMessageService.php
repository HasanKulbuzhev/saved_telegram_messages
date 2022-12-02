<?php

namespace Services;

use Exception;
use JetBrains\PhpStorm\Pure;

class CacheMessageService
{
    private string $file_name;
    private array $messages;

    public function __construct(string $fileName)
    {
        $this->file_name = $fileName;
        if (!$this->issetFile()) {
            $this->setData([]);
        }
        $this->fill();
    }

    private function fill()
    {
        $this->messages = $this->getData(true);
    }

    #[Pure] public function issetFile(): bool
    {
        return file_exists($this->file_name);
    }

    public function getData($actual = false): array
    {
        return $actual ?
            json_decode(file_get_contents($this->file_name), true) :
            $this->messages;
    }

    public function updateValues($key, array $data): bool|int
    {
        $newData = $this->getData();

        if (is_null($key)) {
            $newData[] = $data;
        } else {
            $newData[$key] = $data;
        }

        return $this->setData($newData);
    }

    public function setData(array $data): bool|int
    {
        try {
            return file_put_contents($this->file_name, json_encode($data));
        } catch (Exception $exception) {
            throw $exception;
        } finally {
            $this->fill();
        }
    }

    public function getLastMessageId(): int
    {
        $lastId = 0;

        foreach ($this->getData() as $value) {
            foreach ($value['ids'] as $id) {
                if ($id > $lastId) {
                    $lastId = $id;
                }
            }
        }

        return $lastId;
    }
}