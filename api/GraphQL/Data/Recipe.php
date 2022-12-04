<?php
namespace ShortAPI\GraphQL\Data;

class Recipe {
    public int $id;
    public ?string $title;

    public function __construct(int $id, ?string $title) {
        $this->id = $id;
        $this->title = $title;
    }
}