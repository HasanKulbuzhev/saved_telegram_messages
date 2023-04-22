<?php

namespace Services;

use PDO;

class SQLitePDO extends PDO
{
    function __construct($filename) {
        $filename = realpath($filename);
        parent::__construct('sqlite:' . $filename);

        $key = ftok($filename, 'a');
        $this->sem = sem_get($key);
    }
}