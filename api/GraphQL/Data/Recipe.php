<?php
namespace ShortAPI\GraphQL\Data;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Recipe {
    private Logger $log;

    public int $id;
    public ?string $title;
    public ?string $description;
    public ?string $prep_time;
    public ?string $markdown_recipe;
    public ?string $photo;

    public function __construct(array $properties) {
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../../app.log', Logger::DEBUG));

        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }
    }
}