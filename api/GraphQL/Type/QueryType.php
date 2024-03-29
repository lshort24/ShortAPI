<?php
namespace ShortAPI\GraphQL\Type;

use Exception;
use GraphQL\Type\Definition\Type;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

use ShortAPI\GraphQL\Data\Recipe;
use ShortAPI\GraphQL\Data\Tag;
use ShortAPI\services\DatabaseException;
use ShortAPI\services\RecipeService;
use ShortAPI\services\TagService;

class QueryType extends ObjectType {
    private Logger $log;

    public function __construct() {
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../../app.log', Logger::DEBUG));

        $config = [
            'name' => 'Query',
            'description' => 'Queries for the Good Food app',
            'fields' => [
                'recipe' => [
                    'type' => RecipeType::instance(),
                    'description' => 'Recipe for the given id',
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                    ]
                ],
                'tags' => [
                    'type' => Type::ListOf(TagType::instance()),
                    'description' => 'All possible tags that can be assigned to a recipe.'
                ],
            ],
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
        $record = RecipeService::instance()->getRecipeById($args['id']);
        return new Recipe($record);
    }


    /**
     * @return array
     * @throws DatabaseException
     */
    function resolveTags() : array {
        return array_map(function ($record) {
            return new Tag($record);
        }, TagService::instance()->getAllTags());
    }
}