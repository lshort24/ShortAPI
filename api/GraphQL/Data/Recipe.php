<?php
namespace ShortAPI\GraphQL\Data;

class Recipe {
    public $title;

    public function __construct(array $data) {
        $this->title = $data['title'];
    }
}