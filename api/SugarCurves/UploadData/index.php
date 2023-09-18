<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require __DIR__ . '/../../../vendor/autoload.php';
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\services\DatabaseException;
use ShortAPI\SugarCurves\services\DataService;

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Headers: Accept, Origin, Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function exitWithError(string $message, Logger $log) : void {
    $log->error($message);
    echo json_encode([
        'error' => [
            'message' => $message
        ]
    ]);
    exit;
}

$log = new Logger('sugarCurvesAPI');
$log->pushHandler(new StreamHandler(__DIR__ . '/../../../sugar_curves_api.log', Logger::DEBUG));

// Check file size
if ($_FILES["file"]["size"] > 2 * 1000000) {
    exitWithError("Sorry, your file is too large. Size = {$_FILES["file"]["size"]}.", $log);
}

// Check file type
if ($_FILES['file']['type'] != 'text/csv') {
    exitWithError("Sorry, only text/csv file types are allowed.", $log);
}

$log->debug("Uploading file. Type = {$_FILES['file']['type']}.");

$dataFile = fopen($_FILES['file']['tmp_name'], "r");
if (!$dataFile) {
    exitWithError("Could not upload file {$_FILES['file']['name']}.", $log);
}
$lineNo = 1;
$fromDateString = '2023-08-21';
$data = [];
while (($line = fgets($dataFile)) !== false) {
    if ($lineNo > 100) {
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
    [$dateString, $timeString, $ampm] = explode(' ', $timestampString);

    if ($dateString != $fromDateString) {
        continue;
    }
    $timestamp = DateTime::createFromFormat('Y-m-d h:i A', $timestampString);
    $sqlTimestampString = $timestamp->format('Y-m-d H:i:s');
    $glucose = intval($fields[4]);

    $row = [
        'user_id' => 24,
        'timestamp' => $sqlTimestampString,
        'glucose' => $glucose
    ];
    $data[] = $row;
    $lineNo++;
}
fclose($dataFile);

$rows = 0;
try {
    $rows = DataService::instance()->saveData($data);
}
catch (DatabaseException $ex) {
    exitWithError($ex->getMessage(), $log);
}
catch (Throwable $ex) {
    $message = "Could not save data.";
    $log->error($message, ['ex' => $ex->getMessage()]);
    exitWithError($message, $log);
}

$log->debug("Successfully uploaded $rows row(s).");
http_response_code(200);
$response = [
    'message' => "Successfully uploaded $rows row(s)."
];
echo json_encode($response);
