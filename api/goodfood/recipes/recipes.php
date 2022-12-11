<?php

use ShortAPI\auth\Authorization;
use ShortAPI\config\Database;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../vendor/autoload.php';
//require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/secrets.php';

$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$database = new Database();
/** @var PDO $pdo */
$pdo = $database->getConnection('goodfood', Authorization::GUEST_ROLE);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        get($pdo);
    }
}
catch (throwable $e) {
    echo $e->getMessage();
    http_response_code(500);
}
exit;

/**
 * Handle a GET request
 *
 * @param PDO $pdo
 * @throws Exception
 */
function get(PDO $pdo) {
    $whereClauses = [];
    $params = [];
    $selectFields = 'r.recipe_id AS recipeId, r.title, r.description, r.photo, r.ingredients, r.directions, r.markdown_recipe AS markdownRecipe';

    if (isset($_GET['id'])) {
        if (!preg_match('/^\d+$/', $_GET['id'])) {
            throw new Exception('Invalid recipe id.');
        }
        $whereClauses[] = 'r.recipe_id = :recipe_id';
        $params['recipe_id'] = $_GET['id'];
    }

    if (!empty($_GET['keywords'])) {
        $whereClauses[] = "MATCH (r.title, r.description, r.ingredients, r.directions, r.markdown_recipe) AGAINST (:keywords IN NATURAL LANGUAGE MODE)";
        $params['keywords'] = $_GET['keywords'];

        $tagMatches = searchTags($pdo, $_GET['keywords']);
        if (count($tagMatches) > 0) {
            $ids = implode(',', $tagMatches);
            $whereClauses[] = "r.recipe_id IN ({$ids})";
        }
    }

    if (isset($_GET['summary'])) {
        $selectFields = 'r.recipe_id AS recipeId, r.title, r.description, r.photo';
    }

    $where = '';
    if (count($whereClauses) > 0) {
        $where = 'WHERE ' . implode(' OR ', $whereClauses);
    }

    $sql = "
        SELECT 
            {$selectFields},
            GROUP_CONCAT(DISTINCT l.name ORDER BY l.name ASC SEPARATOR ',') AS tags
        FROM recipes r
        LEFT JOIN recipe_labels rl ON rl.recipe_id = r.recipe_id
        LEFT JOIN labels l ON l.label_id = rl.label_id
        {$where}
        GROUP BY r.recipe_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();

    // Convert recipeId to an integer
    $recipes = array_map(function ($recipe) {
        $recipe->recipeId = intval($recipe->recipeId);
        return $recipe;
    }, $recipes);

    if (isset($_GET['id'])) {
        if (count($recipes) === 0) {
            http_response_code(404);
            echo 'Recipe not found.';
            exit;
        }

        // We should only have one result
        if (count($recipes) > 1) {
            throw new Exception ('Unexpected number of results.');
        }

        http_response_code(200);
        echo json_encode($recipes[0]);
        exit;
    }
    http_response_code(200);
    echo json_encode($recipes);
}


/**
 * Search tags for keywords
 *
 * @param PDO $pdo
 * @param string $keywords
 * @return int[]
 */
function searchTags(PDO $pdo, string $keywords) : array {
    $sql = "
        SELECT recipe_id FROM labels
        JOIN recipe_labels ON recipe_labels.label_id = labels.label_id
        WHERE MATCH (name) AGAINST (:keywords IN NATURAL LANGUAGE MODE)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['keywords' => $keywords]);
    $results = $stmt->fetchAll();
    return array_map(function($recipe) {
        return intval($recipe->recipe_id);
    }, $results);
}


/**
 * Handle a POST request
 *
 * @param PDO $pdo
 */
function post(PDO $pdo) {
    $data = json_decode(file_get_contents("php://input"));

    $sql = "
        INSERT INTO recipes(title) VALUES(:title)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['title' => $data->title]);
}


/**
 * Handle a PUT request
 *
 * @param PDO $pdo
 */
function put(PDO $pdo) {
    $data = json_decode(file_get_contents("php://input"));

    $sql = "
        UPDATE recipes SET title = :title
        WHERE recipe_id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['recipe_id' => $data->recipe_id, 'title' => $data->title]);
}