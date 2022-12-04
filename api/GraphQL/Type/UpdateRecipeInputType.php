<?php

namespace ShortAPI\GraphQL\Type;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\GraphQL\Data\Recipe;

class UpdateRecipeInputType extends InputObjectType
{
    private Logger $log;
    private static ?self $instance = null;

    public static function instance() : self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../../app.log', Logger::DEBUG));

        $config = [
            'name' => 'UpdateRecipeInput',
            'description' => '',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::id()),
                    'description' => 'Id of the recipe to update'
                ],
                'title' => [
                    'type' => Type::string(),
                    'description' => 'Title of the recipe'
                ],
            ],
            'parseValue' => function (array $values) : Recipe {
                $this->log->debug('parsed value arg');
                return new Recipe(
                    $values['id'],
                    $values['title'],
                );
            }
        ];
        parent::__construct($config);
    }
}