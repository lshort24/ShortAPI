<?php

namespace ShortAPI\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\GraphQL\Data\Tag;

class TagType extends ObjectType {
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
            'name' => 'Tag',
            'description' => 'A label that can be added to a recipe for better search results',
            'fields' => [
                'id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'Id for the tag',
                ],
                'name' => [
                    'type' => Type::string(),
                    'description' => 'Label to display',
                ],
            ],
            'resolveField' => function ($rootValue, array $args, $context, ResolveInfo $info) {
                $resolver = 'resolve' . ucfirst($info->fieldName);
                return $this->{$resolver}($rootValue, $args, $context, $info);
            },
        ];

        parent::__construct($config);
    }

    public function resolveId(Tag $tag) : int {
        return $tag->id;
    }

    public function resolveName(Tag $tag) : string {
        return $tag->name;
    }
}