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

$log = new Logger('sugarCurvesAPI');
$log->pushHandler(new StreamHandler(__DIR__ . '/../../../sugar_curves_api.log', Logger::DEBUG));

function exitWithError(string $message, Logger $log) : void {
    $log->error($message);
    echo json_encode([
        'error' => [
            'message' => $message
        ]
    ]);
    exit;
}

// Check file size
if ($_FILES["file"]["size"] > 2 * 1000000) {
    exitWithError("Sorry, your file is too large. Size = {$_FILES["file"]["size"]}.", $log);
}

// Check file type
if ($_FILES['file']['type'] != 'text/csv') {
    exitWithError("Sorry, only text/csv file types are allowed.", $log);
}

/*
$startDate = $_POST['startDate'];
$endDate = $_POST['endDate'];
$log->debug("Deleting old data entries.", ['startDate' => $startDate, 'endDate' => $endDate]);
try {
    $numRows = DataService::instance()->clearData(24, $startDate, $endDate);
    $log->debug("Deleted $numRows rows.");
}
catch (DatabaseException $ex) {
    exitWithError($ex->getMessage(), $log);
}
*/

$minTimestampString = '2023-10-01';
$minTimestamp = strtotime($minTimestampString);
if (!$minTimestamp) {
    exitWithError(htmlentities("Invalid minimum timestamp '$minTimestampString'."), $log);
}
$lastTimestamp = '';
try {
    $lastTimestampString = DataService::instance()->getMaxTimestamp(24);
    if ($lastTimestampString) {
        $lastTimestamp = strtotime($lastTimestampString);
        if (!$lastTimestamp) {
            exitWithError(htmlentities("Invalid last timestamp '$minTimestampString'."), $log);
        }
    }
    else {
        $lastTimestamp = $minTimestamp;
    }
}
catch (Throwable $ex) {
    exitWithError($ex->getMessage(), $log);
}
if (!$lastTimestamp) {
    exitWithError("Something went wrong determining the last timestamp.", $log);
}
$log->debug("Uploading file.", ['type' => $_FILES['file']['type']]);

$dataFile = fopen($_FILES['file']['tmp_name'], "r");
if (!$dataFile) {
    exitWithError("Could not upload file {$_FILES['file']['name']}.", $log);
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
        exitWithError(htmlentities("Invalid timestamp '$timestampString'."), $log);
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
        $message = "Could not create SQL timestamp";
        $log->error($message, ['ex' => $ex->getMessage(), ['timestampString' => $timestampString]]);
        exitWithError(htmlentities($message), $log);
    }
    if (!$sqlTimestamp) {
        exitWithError("Something went wrong creating the SQL timestamp.", $log);
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
    exitWithError("There were no new records to upload.", $log);
}

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
