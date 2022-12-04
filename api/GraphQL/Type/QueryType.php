<?php
namespace ShortAPI\GraphQL\Type;

use Exception;
use GraphQL\Type\Definition\Type;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

use ShortAPI\GraphQL\Data\Recipe;
use ShortAPI\Entities\RecipeEntity;

class QueryType extends ObjectType {
    private Logger $log;

    public function __construct() {
        $this->log = new Logger('graphql');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../../app.log', Logger::DEBUG));

        $recipeType = new RecipeType();

        $config = [
            'name' => 'Query',
            'fields' => function () use (
                $recipeType
            ){
                return [
                    'recipe' => [
                        'type' => $recipeType,
                        'description' => 'A Recipe',
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                        ]
                    ]
                ];
            },
            'resolveField' => function ($rootValue, array $args, $context, ResolveInfo $info) {
                $resolver = 'resolve' . ucfirst($info->fieldName);
                return $this->{$resolver}($rootValue, $args, $context, $info);
            },
        ];

        return parent::__construct($config);
    }

    /**
     * @param $rootValue
     * @param array $args
     * @return Recipe
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     */
    function resolveRecipe($rootValue, array $args) : ?Recipe {
        $record = RecipeEntity::instance()->getRecipeById($args['id']);
        return new Recipe($record);
    }
}