<?php
namespace ShortAPI\services;

use Exception;
use PDO;
use ShortAPI\config\Database;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Throwable;

class RecipeService
{
    static ?self $instance = null;
    private Database $database;
    private PDO $pdo;
    private Logger $log;

    public static function instance() : self {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }


    public function __construct() {
        $this->database = new Database();
        $this->pdo = $this->database->getConnection('goodfood');
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../app.log', Logger::DEBUG));
    }


    /**
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function getRecipeById(int $id) : array {
        if ($id <= 0) {
            $this->log->debug('No id was specified.');
            throw new Exception("Could not access recipe.");
        }

        $fields = 'recipe_id as id, title';
        $params = [
            'recipe_id' => $id
        ];
        $sql = "SELECT $fields FROM recipes WHERE recipe_id = :recipe_id";
        // TODO: can we change this like the update?

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
        catch (Throwable $ex) {
            $this->log->debug("Could not fetch recipe with id $id.");
            throw new Exception("Could not access recipe.");
        }

        $records = $stmt->fetchAll();

        foreach ($records as $recipe) {
            return $recipe;
        }

        $this->log->debug("Recipe with id $id was not found.");
        throw new Exception("The recipe you requested was not found.");
    }


    /**
     * @param array $recipeArray
     * @return array
     * @throws Exception
     */
    public function updateRecipeById(array $recipeArray) : array {
        if ($recipeArray['id'] <= 0) {
            $this->log->debug('No id was specified.');
            throw new Exception("Could not access recipe.");
        }
        $id = $recipeArray['id'];
        $set = [];
        $params = [
            'id' => $id,
        ];
        foreach ($recipeArray as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            $set[] = "$key = :$key";
            $params[$key] = $value;
        }
        $setClause = "SET " . implode(', ', $set);
        $sql = "UPDATE recipes $setClause WHERE recipe_id = :id";
        $this->log->debug('SQL', ['sql' => $sql, 'params' => $params]);

        try {
            $this->pdo->prepare($sql)->execute($params);
        }
        catch (Throwable $ex) {
            $this->log->debug('Could not update recipe', ['recipe' => $recipeArray]);
            throw new Exception('Could not update recipe.');
        }

        $this->log->debug('after save');

        $record = $this->getRecipeById($id);

        $this->log->debug('after re-fetch', ['record' => $record]);
        return $record;
    }
}