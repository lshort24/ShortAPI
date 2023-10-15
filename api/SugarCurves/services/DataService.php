<?php
namespace ShortAPI\SugarCurves\services;

use ShortAPI\auth\Authorization;
use ShortAPI\config\Database;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\services\DatabaseException;
use Throwable;

class DataService
{
    const SERVICE_NAME = 'Data Service';
    const UPSERT_DATA_SQL = <<< SQL
       INSERT INTO data (user_id, timestamp, glucose) VALUES(:user_id, :timestamp, :glucose)
       ON DUPLICATE KEY UPDATE glucose = :glucose
SQL;

    const CLEAR_DATA_SQL = <<< SQL
    DELETE FROM data WHERE user_id = :user_id AND timestamp IS BETWEEN :start_date AND :end_date
SQL;

    const MAX_TIMESTAMP_SQL = <<< SQL
    SELECT MAX(timestamp) FROM data WHERE user_id = :user_id
SQL;


    static ?self $instance = null;
    private Database $database;
    private Logger $log;

    public static function instance() : self {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function __construct() {
        $this->database = new Database();
        $this->log = new Logger('sugarCurvesAPI');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../../sugar_curves_api.log', Logger::DEBUG));
    }

    /**
     * @param array $data multiple rows of user_id, timestamp and glucose
     * @return int
     * @throws DatabaseException
     */
    public function saveData(array $data) : int {
        $rows = 0;
        try {
            $pdo = $this->database->getConnection('sugar_curves', Authorization::USER_ROLE);
            $statement = $pdo->prepare(self::UPSERT_DATA_SQL);
        }
        catch (Throwable $ex) {
            $this->log->error(self::SERVICE_NAME . ": Could not create database connection.", ['ex' => $ex->getMessage()]);
            throw new DatabaseException('Could not create database connection.');
        }

        try {
            $pdo->beginTransaction();
            foreach ($data as $row) {
                $statement->execute($row);
                $rows++;
            }
            $pdo->commit();
        }
        catch (Throwable $ex) {
            $pdo->rollBack();
            $this->log->error(self::SERVICE_NAME . ": Could not insert data", ['ex' => $ex->getMessage(), 'row' => $row]);
            throw new DatabaseException('Could not insert data.');
        }

        return $rows;
    }


    /**
     * Clear data records for a given user and date range
     *
     * @param int $userId
     * @param string $startDate
     * @param string $endDate
     * @return int
     * @throws DatabaseException
     */
    public function clearData(int $userId, string $startDate, string $endDate) : int {
        if ($userId <= 0) {
            $message = 'Invalid user id.';
            $this->log->error(self::SERVICE_NAME . ": $message", ['userId' => $userId]);
            throw new DatabaseException($message);
        }
        if (!strtotime($startDate)) {
            $message = 'Invalid start date.';
            $safeDate = htmlentities($startDate);
            $this->log->error(self::SERVICE_NAME . ": $message", ['startDate' => $safeDate]);
            throw new DatabaseException($message);
        }
        if (!strtotime($endDate)) {
            $message = 'Invalid end date.';
            $safeDate = htmlentities($endDate);
            $this->log->error(self::SERVICE_NAME . ": $message", ['endDate' => $safeDate]);
            throw new DatabaseException($message);
        }
        try {
            $pdo = $this->database->getConnection('sugar_curves', Authorization::USER_ROLE);

        }
        catch (Throwable $ex) {
            $this->log->error(self::SERVICE_NAME . ": Could not create database connection.", ['ex' => $ex->getMessage()]);
            throw new DatabaseException('Could not create database connection.');
        }

        $params = [
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        try {
            $statement = $pdo->prepare(self::CLEAR_DATA_SQL);
            $statement->execute($params);
            return $statement->rowCount();
        }
        catch (Throwable $ex) {
            $this->log->error(self::SERVICE_NAME . ": Could not clear data from $startDate to $endDate.", ['ex' => $ex->getMessage()]);
            throw new DatabaseException('Could not clear database records.');
        }
    }


    /**
     * Find the max timestamp for a given user
     *
     * @param int $userId
     * @return string
     * @throws DatabaseException
     */
    public function getMaxTimestamp(int $userId) : string {
        try {
            $pdo = $this->database->getConnection('sugar_curves', Authorization::USER_ROLE);
        }
        catch (Throwable $ex) {
            $this->log->error(self::SERVICE_NAME . ": Could not create database connection.", ['ex' => $ex->getMessage(), 'userId' => $userId]);
            throw new DatabaseException('Could not create database connection.');
        }

        $params = [
            'user_id' => $userId
        ];
        try {
            $statement = $pdo->prepare(self::MAX_TIMESTAMP_SQL);
            $statement->execute($params);
            $numRows = $statement->rowCount();
            if ($numRows === 0) {
                $this->log->debug("There are no records yet.", ['userId' => $userId]);
                return '';
            }
            $maxTimestamp = $statement->fetchColumn();
            $this->log->debug("Results from max timestamp", ['results' => $maxTimestamp, 'userId' => $userId]);
            return $maxTimestamp;
        }
        catch (Throwable $ex) {
            $message = "Could not fetch max timestamp.";
            $this->log->error(self::SERVICE_NAME . ": $message", ['ex' => $ex->getMessage(), 'userId' => $userId]);
            throw new DatabaseException($message);
        }
    }
}