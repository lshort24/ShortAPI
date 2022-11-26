<?php
namespace ShortAPI\GraphQL\Type;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

use ShortAPI\GraphQL\Data\Recipe;

class RecipeType extends ObjectType {
    private $log;

    public function __construct() {
        $this->log = new Logger('graphql');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../../graphql.log', Logger::DEBUG));

        $config = [
            'name' => 'Recipe',
            'fields' => function() {
                return [
                    'title' => Type::string()
                ];
            },
            'resolveField' => function ($rootValue, array $args, $context, ResolveInfo $info) {
                $resolver = 'resolve' . ucfirst($info->fieldName);
                return $this->{$resolver}($rootValue, $args, $context, $info);
            },
        ];

        parent::__construct($config);
    }

    public function resolveTitle(Recipe $recipe) : string {
        return $recipe->title;
    }
}