<?php

namespace ShortAPI\GraphQL\Type;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

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
            'description' => 'Recipe values to update',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::id()),
                    'description' => 'Id of the recipe to update'
                ],
                'title' => [
                    'type' => Type::string(),
                    'description' => 'Title of the recipe'
                ],
                'description' => [
                    'type' => Type::string(),
                    'description' => 'Description of the recipe'
                ],
                'prepTime' => [
                    'type' => Type::string(),
                    'description' => 'Time it takes to make the recipe'
                ],
                'photo' => [
                    'type' => Type::string(),
                    'description' => 'Photo of the dish made from the recipe'
                ],
                'markdownRecipe' => [
                    'type' => Type::string(),
                    'description' => 'The ingredients and directions in markdown format',
                ]
            ],
        ];
        parent::__construct($config);
    }
}