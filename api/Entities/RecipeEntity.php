<?php
namespace ShortAPI\Entities;

use Exception;
use PDO;
use ShortAPI\config\Database;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class RecipeEntity
{
    static $instance;
    private $database;
    private $pdo;
    private $log;

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

        $this->log = new Logger('graphql');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../graphql.log', Logger::DEBUG));
    }


    /**
     * @param int $id
     * @return array
     * @throws Exception
     */
    public function getRecipeById(int $id) : array {
        if ($id <= 0) {
            throw new Exception("Id is required.");
        }

        $fields = 'title';
        $params = [
            'recipe_id' => $id
        ];
        $sql = "SELECT $fields FROM recipes WHERE recipe_id = :recipe_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        foreach ($records as $recipe) {
            return $recipe;
        }

        throw new Exception("Not found");
    }
}