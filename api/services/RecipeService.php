<?php
namespace ShortAPI\services;

use PDO;
use ShortAPI\auth\Authorization;
use ShortAPI\config\Database;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Throwable;

class RecipeService
{
    static ?self $instance = null;
    private Database $database;
    private Logger $log;

    public static function instance() : self {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }


    public function __construct() {
        $this->database = new Database();
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../app.log', Logger::DEBUG));
    }


    /**
     * @param int $id
     * @return array
     * @throws DatabaseException
     */
    public function getRecipeById(int $id) : array {
        // For testing error handling
        // throw new DatabaseException('Could not access recipe.');
        if (!Authorization::instance()->hasRole(Authorization::GUEST_ROLE)) {
            $this->log->error("Recipe Service: Permission denied.");
            throw new DatabaseException("Permission denied.");
        }

        if ($id <= 0) {
            $this->log->debug("Recipe Service: No recipe id was specified.");
            throw new DatabaseException("Could not access recipe.");
        }

        $fields = 'recipe_id as id, title, description, prep_time, markdown_recipe';
        $sql = "SELECT $fields FROM recipes WHERE recipe_id = :recipe_id";

        try {
            $pdo = $this->database->getConnection('goodfood', Authorization::GUEST_ROLE);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam('recipe_id', $id, PDO::PARAM_INT);
            $stmt->execute();
        }
        catch (Throwable $ex) {
            $this->log->debug("Recipe Service: Could not fetch recipe with id $id.");
            throw new DatabaseException("Could not access recipe.");
        }

        $records = $stmt->fetchAll();

        foreach ($records as $recipe) {
            return $recipe;
        }

        $this->log->debug("Recipe Service: Recipe with id $id was not found.");
        throw new DatabaseException("The recipe you requested was not found.");
    }


    /**
     * @param array $recipeArray
     * @return array
     * @throws DatabaseException
     */
    public function updateRecipeById(array $recipeArray) : array {
        // For testing error handling
        // throw new DatabaseException('Could not save recipe.');
        if (!Authorization::instance()->hasRole(Authorization::ADMIN_ROLE)) {
            $this->log->error("Recipe Service: The current user does not have the admin role.");
            throw new DatabaseException("Permission denied.");
        }

        if ($recipeArray['id'] <= 0) {
            $this->log->error("Recipe Service: No recipe id was specified.");
            throw new DatabaseException("Could not access recipe.");
        }

        $id = $recipeArray['id'];
        $set = [];
        $params = [
            'id' => [
                'value' => $id,
                'type' => PDO::PARAM_INT
            ]
        ];
        foreach ($recipeArray as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            $dbField = $key;
            if ($dbField === 'prepTime') {
                $dbField = 'prep_time';
            }
            if ($dbField === 'markdownRecipe') {
                $dbField = 'markdown_recipe';
            }
            $set[] = "$dbField = :$dbField";
            $params[$dbField] = [
                'value' => $value,
                'type' => PDO::PARAM_STR
            ];
        }
        $setClause = "SET " . implode(', ', $set);
        $sql = "UPDATE recipes $setClause WHERE recipe_id = :id";
        try {
            $pdo = $this->database->getConnection('goodfood', Authorization::ADMIN_ROLE);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $info) {
                $stmt->bindParam($key, $info['value'], $info['type']);
            }
            $stmt->execute();
        }
        catch (Throwable $ex) {
            $this->log->error("Recipe Service: Could not update recipe", ['recipe' => $recipeArray]);
            throw new DatabaseException('Could not update recipe.');
        }

        return $this->getRecipeById($id);
    }
}