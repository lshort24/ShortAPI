<?php
namespace ShortAPI\GraphQL\Type;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

use ShortAPI\GraphQL\Data\Recipe;
use ShortAPI\GraphQL\Data\Tag;
use ShortAPI\services\DatabaseException;
use ShortAPI\services\TagService;

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
                'photo' => [
                    'type' => Type::string(),
                    'description' => 'Photo of the dish made from the recipe.'
                ],
                'markdownRecipe' => [
                    'type' => Type::string(),
                    'description' => 'The ingredients and directions in markdown format'
                ],
                'tags' => [
                    'type' => Type::listOf(TagType::instance()),
                    'description' => 'Labels that can be added to a recipe for better search results.'
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
        return $recipe->description ?? '';
    }

    public function resolvePrepTime(Recipe $recipe) : ?string {
        return $recipe->prep_time ?? '';
    }

    public function resolvePhoto(Recipe $recipe) : ?string {
        return $recipe->photo;
    }

    public function resolveMarkdownRecipe(Recipe $recipe) : ?string {
        return $recipe->markdown_recipe ?? '';
    }

    /**
     * @param Recipe $recipe
     * @return array
     * @throws DatabaseException
     */
    public function resolveTags(Recipe $recipe) : array {
        return array_map(function ($record) {
            return new Tag([
                'id' => $record['label_id'],
                'name' => $record['tag_name']
            ]);
        }, TagService::instance()->getTagsByRecipeId($recipe->id));
    }
}