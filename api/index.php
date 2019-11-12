<?php

require_once './config/Database.php';
require_once './handlers/TimelineApiHandler.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo("Yay!");
// instantiate database and product object
$database = new Database();
$db = $database->getConnection();

// initialize object
$timeline = new TimelineApiHandler($db);

// query products
$stmt = $timeline->read();
$num = $stmt->rowCount();
echo "num: {$num}";

if ($num > 0 ) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // extract row
        // this will make $row['name'] to
        // just $name only
        $name = null;
        var_dump($row);
        extract($row);
        echo $name;
    }
}

echo "done";
