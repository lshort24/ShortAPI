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
            $this->log->error(self::SERVICE_NAME . ": The user does not have the guest role.");
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
            $this->log->debug(self::SERVICE_NAME . ": Could not fetch tags for recipe with id $recipeId.");
            throw new DatabaseException("Could not access recipe tags.");
        }

        return $stmt->fetchAll();
    }


    /**
     * @return array
     * @throws DatabaseException
     */
    public function getAllTags() : array {
        if (!Authorization::instance()->hasRole(Authorization::GUEST_ROLE)) {
            $this->log->error(self::SERVICE_NAME . ": The user does not have the guest role.");
            throw new DatabaseException("Permission denied.");
        }

        $sql = "SELECT label_id as id, name FROM labels ORDER BY name";

        try {
            $pdo = $this->database->getConnection('goodfood', Authorization::GUEST_ROLE);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        catch (Throwable $ex) {
            $this->log->debug(self::SERVICE_NAME . ": Could not fetch all tags.");
            throw new DatabaseException("Could not access recipe tags.");
        }

        return $stmt->fetchAll();
    }


    /**
     * @param int $recipeId
     * @param array $tagIds
     * @return array
     * @throws DatabaseException
     */
    public function updateTags(int $recipeId, array $tagIds) : array {
        if (!Authorization::instance()->hasRole(Authorization::ADMIN_ROLE)) {
            $this->log->error(self::SERVICE_NAME . ": The user does not have the admin role.");
            throw new DatabaseException("Permission denied.");
        }

        // Get the current set of tags
        $existingTagIds = array_column($this->getTagsByRecipeId($recipeId), 'label_id');

        // See which ones need to be added
        $toAdd = array_values(array_filter($tagIds, function ($tagId) use ($existingTagIds) {
            return !in_array($tagId, $existingTagIds);
        }));

        $pdo = $this->database->getConnection('goodfood', Authorization::ADMIN_ROLE);
        if (!empty($toAdd)) {
            $sql = "INSERT INTO recipe_labels (recipe_id, label_id) VALUES (:recipe_id, :label_id)";
            $stmt= $pdo->prepare($sql);
            $stmt->bindParam('recipe_id', $recipeId, PDO::PARAM_INT);
            foreach ($toAdd as $labelId) {
                $stmt->bindParam('label_id', $labelId, PDO::PARAM_INT);
                $stmt->execute();
                $this->log->debug(self::SERVICE_NAME . ": Added label $labelId.");
            }
        }

        // See which ones need to be deleted
        $toDelete = array_values(array_filter($existingTagIds, function ($existingTagId) use ($tagIds) {
            return !in_array($existingTagId, $tagIds);
        }));

        if (!empty($toDelete)) {
            $sql = "DELETE FROM recipe_labels WHERE recipe_id = :recipe_id AND label_id = :label_id";
            $stmt= $pdo->prepare($sql);
            $stmt->bindParam('recipe_id', $recipeId, PDO::PARAM_INT);
            foreach ($toDelete as $labelId) {
                $stmt->bindParam('label_id', $labelId, PDO::PARAM_INT);
                $stmt->execute();
                $this->log->debug(self::SERVICE_NAME . ": Deleted label $labelId.");
            }
        }

        return $this->getTagsByRecipeId($recipeId);
    }
}