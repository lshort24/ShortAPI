<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\auth\Authorization;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/config/secrets.php';

$log = new Logger('api');
$log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::DEBUG));

// required headers
$secrets = getSecrets();
$origin = ($_SERVER['REMOTE_ADDR'] === $secrets['my_ip']) ? "http://localhost:3000" : 'https://shortsrecipes.com';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Headers: Accept, Origin, Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/^Bearer (.*)$/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $token = $matches[1];
        Authorization::instance()->setToken($token);
    }
}

try {
    $isAdmin = Authorization::instance()->hasRole(Authorization::ADMIN_ROLE);
}
catch (Throwable $ex) {
    $log->error("Could not determine if user is an admin.", ['ex' => $ex->getMessage()]);
    $isAdmin = false;
}

if (!$isAdmin) {
    $log->error("Upload File: The current user does not have the admin role.");
    echo json_encode([
        'error' => [
            'message' => "Permission denied."
        ]
    ]);
    exit;
}

$targetDir = '../photos/';
$basename = basename($_FILES["file"]["name"]);
$targetFile = $targetDir . $basename;
$uploadOk = true;
$imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

// Check if image file is an actual image or fake image
$imageInfo = getimagesize($_FILES["file"]["tmp_name"]);
if (!$imageInfo) {
    $message = "File is not an image.";
    $log->error($message);
    echo json_encode([
        'error' => [
            'message' => $message
        ]
    ]);
    exit;
}

// Check if file already exists
if (file_exists($targetFile)) {
    $message = "A photo with that name already exists. Rename the file and try again.";
    $log->error($message);
    echo json_encode([
        'error' => [
            'message' => $message,
        ]
    ]);
    exit;
}

// Check file size
if ($_FILES["file"]["size"] > 500000) {
    $message = "Sorry, your file is too large.";
    $log->error($message);
    echo json_encode([
        'error' => [
            'message' => $message
        ]
    ]);
    exit;
}

// Allow certain file formats
if($imageFileType != "jpg" &&
    $imageFileType != "png" &&
    $imageFileType != "jpeg" &&
    $imageFileType != "gif"
) {
    $message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    $log->error($message);
    echo json_encode([
        'error' => [
            'message' => $message
        ]
    ]);
    exit;
}

// Save the file
try {
    if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
        $message = "The file ". htmlspecialchars( basename( $_FILES["file"]["name"])). " has been uploaded.";
        $log->debug($message, ['image' => $imageInfo]);
        echo json_encode([
            'message' => $message,
            'photoFileName' => $basename,
        ]);
        exit;
    }
    else {
        $log->error("Could not move file to permanent name.");
    }
}
catch (Throwable $ex) {
    $log->error("Could not upload file.", ['ex' => $ex->getMessage()]);
}

echo json_encode([
    'error' => [
        'message' => "Sorry, there was an error uploading your file."
    ]
]);
exit;
