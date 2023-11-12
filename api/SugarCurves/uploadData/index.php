<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Headers: Accept, Origin, Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header('Content-Type: application/json');

require __DIR__ . '/../../../vendor/autoload.php';
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\services\DatabaseException;
use ShortAPI\SugarCurves\services\DataService;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$log = new Logger('sugarCurvesAPI');
$log->pushHandler(new StreamHandler(__DIR__ . '/../../../sugar_curves_api.log', Logger::DEBUG));

function exitWithUploadDataResponse(int $code, bool $status, string $statusMessage) : void {
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'statusMessage' => $statusMessage
    ]);
    exit;
}

// Authorize
$token = null;
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/^Bearer (.*)$/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    $log->error('No access token was specified.');
    exitWithUploadDataResponse(403, false, 'Access denied.');
}

// Check file size
if ($_FILES["file"]["size"] > 2 * 1000000) {
    $log->error("Your file is too large.", ['size' => $_FILES["file"]["size"]]);
    exitWithUploadDataResponse(200, false, "Upload error");
}

// Check file type
if ($_FILES['file']['type'] != 'text/csv') {
    $log->error("Only text/csv file types are allowed.", ['type' => $_FILES['file']['type']]);
    exitWithUploadDataResponse(200, false, "Upload error");
}

// Upload data
$minTimestampString = '2023-10-01';
$minTimestamp = strtotime($minTimestampString);
if (!$minTimestamp) {
    $log->error("Invalid minimum timestamp", ['timestamp' => $minTimestampString]);
    exitWithUploadDataResponse(200, false, "Upload error");
}
$lastTimestamp = '';
try {
    $lastTimestampString = DataService::instance()->getMaxTimestamp(24);
    if ($lastTimestampString) {
        $lastTimestamp = strtotime($lastTimestampString);
        if (!$lastTimestamp) {
            $log->error("Invalid last timestamp", ['timestamp' => $lastTimestampString]);
            exitWithUploadDataResponse(200, false, "Upload error");
        }
    }
    else {
        $lastTimestamp = $minTimestamp;
    }
}
catch (Throwable $ex) {
    $log->error("Exception", ['ex' => $ex->getMessage()]);
    exitWithUploadDataResponse(200, false, "Upload error");
}
if (!$lastTimestamp) {
    $log->error("Something went wrong determining the last timestamp.");
    exitWithUploadDataResponse(200, false, "Upload error");
}
$log->debug("Uploading file.", ['type' => $_FILES['file']['type']]);

$dataFile = fopen($_FILES['file']['tmp_name'], "r");
if (!$dataFile) {
    $log->error("Could not upload file", ['name' => $_FILES['file']['name']]);
    exitWithUploadDataResponse(200, false, "Upload error");
}
$lineNo = 0;
$data = [];
$showProcessMessage = true;
while (($line = fgets($dataFile)) !== false) {
    $lineNo++;
    if ($lineNo <= 2) {
        // Skip header lines
        continue;
    }
    if ($lineNo > 50000) {
        $log->error("Exiting early because of too many lines.");
        break;
    }
    // Device, Serial Number, Timestamp (Y-m-d h:i A), Record Type, Glucose
    $fields = explode(',', $line);

    // Record types:
    // 0 - auto scan
    // 1 - manual scan
    // 6 - note
    $recordType = $fields[3];
    if ($recordType != 0) {
        continue;
    }
    $timestampString = $fields[2];
    $timestamp = strtotime($timestampString);
    if (!$timestamp) {
        $log->error("Invalid timestamp", ['timestamp' => $timestampString]);
        exitWithUploadDataResponse(200, false, "Upload error");
    }
    if ($timestamp <= $lastTimestamp) {
        continue;
    }

    if ($showProcessMessage) {
        $log->debug("Processing record with timestamp '$timestampString'.");
        $showProcessMessage = false;
    }

    $sqlTimestamp = '';
    try {
        $timestampDate = new DateTime($timestampString);
        $sqlTimestamp = $timestampDate->format('Y-m-d H:i:s');
    }
    catch (Throwable $ex) {
        $log->error("Could not create SQL timestamp", ['ex' => $ex->getMessage(), 'timestampString' => $timestampString]);
        exitWithUploadDataResponse(200, false, "Upload error");
    }
    if (!$sqlTimestamp) {
        $log->error("Something went wrong creating the SQL timestamp.");
        exitWithUploadDataResponse(200, false, "Upload error");
    }
    $glucose = intval($fields[4]);

    $row = [
        'user_id' => 24,
        'timestamp' => $sqlTimestamp,
        'glucose' => $glucose
    ];
    $data[] = $row;
}
fclose($dataFile);

if (count($data) === 0) {
    exitWithUploadDataResponse(200, true, "There were no new records to upload.");
}

$rows = 0;
try {
    $rows = DataService::instance()->saveData($data);
}
catch (DatabaseException $ex) {
    $log->error("Could not save uploaded data to database.", ['ex' => $ex->getMessage()]);
    exitWithUploadDataResponse(200, false, "Upload error");
}
catch (Throwable $ex) {
    $log->error("Could not save uploaded data.", ['ex' => $ex->getMessage()]);
    exitWithUploadDataResponse(200, false, "Upload error");
}

exitWithUploadDataResponse(200, true, "Successfully uploaded $rows row(s).");