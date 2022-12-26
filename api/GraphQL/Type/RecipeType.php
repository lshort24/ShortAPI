<?php
namespace ShortAPI\GraphQL\Type;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

use ShortAPI\GraphQL\Data\Recipe;

class RecipeType extends ObjectType {
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
            'name' => 'Recipe',
            'description' => 'A recipe in the Good Food app',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'Id for the recipe',
                ],
                'title' => [
                    'type' => Type::string(),
                    'description' => 'Title for the recipe',
                ],
                'description' => [
                    'type' => Type::string(),
                    'description' => 'Description of the recipe',
                ],
                'prepTime' => [
                    'type' => Type::string(),
                    'description' => 'Time it takes to make the recipe',
                ],
                'markdownRecipe' => [
                    'type' => Type::string(),
                    'description' => 'The ingredients and directions in markdown format'
                ]
            ],
            'resolveField' => function ($rootValue, array $args, $context, ResolveInfo $info) {
                $resolver = 'resolve' . ucfirst($info->fieldName);
                return $this->{$resolver}($rootValue, $args, $context, $info);
            },
        ];

        parent::__construct($config);
    }

    public function resolveId(Recipe $recipe) : int {
        return $recipe->id;
    }

    public function resolveTitle(Recipe $recipe) : string {
        return $recipe->title;
    }

    public function resolveDescription(Recipe $recipe) : string {
        return $recipe->description;
    }

    public function resolvePrepTime(Recipe $recipe) : ?string {
        return $recipe->prep_time;
    }

    public function resolveMarkdownRecipe(Recipe $recipe) : ?string {
        return $recipe->markdown_recipe;
    }
}