<?php

namespace ShortAPI\GraphQL\Type;

use Exception;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\GraphQL\Data\Recipe;
use ShortAPI\services\RecipeService;

class MutationType extends ObjectType
{
    private Logger $log;

    public function __construct() {
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../../app.log', Logger::DEBUG));

        $config =  [
            'name' => 'Mutation',
            'description' => 'Mutations for the Good Food app',
            'fields' => [
                'sum' => [
                    'type' => Type::int(),
                    'args' => [
                        'x' => ['type' => Type::int()],
                        'y' => ['type' => Type::int()],
                    ],
                    'resolve' => static fn ($calc, array $args): int => $args['x'] + $args['y'],
                ],
                'updateRecipe' => [
                    'type' => Type::nonNull(RecipeType::instance()),
                    'args' => [
                        'input' => UpdateRecipeInputType::instance(),
                    ],
                    'resolve' => function ($info, array $args) : Recipe {
                        $this->log->debug("mutation input", ['info' => $info, 'args' => $args]);
                        $args = $args['input'];
                        // TODO: validate arguments
                        try {
                            $record = RecipeService::instance()->updateRecipeById($args);
                            return new Recipe($record);
                        }
                        catch (Exception $ex) {
                            $this->log->debug('Could not update recipe', ['ex' => $ex]);
                            throw $ex;
                        }
                    }
                ],
            ],
        ];

        parent::__construct($config);
    }
}