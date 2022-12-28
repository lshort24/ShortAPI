<?php

namespace ShortAPI\services;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PDO;
use ShortAPI\auth\Authorization;
use ShortAPI\config\Database;
use Throwable;

class TagService
{
    const SERVICE_NAME = 'Tag Service';

    static ?self $instance = null;

    private Database $database;
    private Logger $log;

    public static function instance(): self
    {
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
     * @param int $recipeId
     * @return array
     * @throws DatabaseException
     */
    public function getTagsByRecipeId(int $recipeId) : array {
        if (!Authorization::instance()->hasRole(Authorization::GUEST_ROLE)) {
            $this->log->error(self::SERVICE_NAME . ": Permission denied.");
            throw new DatabaseException("Permission denied.");
        }

        if ($recipeId <= 0) {
            $this->log->debug(self::SERVICE_NAME . ": No recipe id was specified.");
            throw new DatabaseException("Could not access recipe tags.");
        }

        $sql = <<< SQL
            SELECT labels.label_id, labels.name AS tag_name
            FROM recipe_labels 
            JOIN labels ON labels.label_id = recipe_labels.label_id
            WHERE recipe_id = :recipe_id
SQL;


        try {
            $pdo = $this->database->getConnection('goodfood', Authorization::GUEST_ROLE);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam('recipe_id', $recipeId, PDO::PARAM_INT);
            $stmt->execute();
        }
        catch (Throwable $ex) {
            $this->log->debug(self::SERVICE_NAME . ": Could not fetch recipe with id $recipeId.");
            throw new DatabaseException("Could not access recipe tags.");
        }

        return $stmt->fetchAll();
    }
}