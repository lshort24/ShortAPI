<?php

namespace ShortAPI\GraphQL\Data;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Tag
{
    private Logger $log;

    public int $id;
    public string $name;

    public function __construct(array $properties) {
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../../app.log', Logger::DEBUG));

        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }
}