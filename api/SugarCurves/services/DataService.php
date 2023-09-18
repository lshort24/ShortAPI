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
        // TODO: Find the min and max timestamp for each day. When processing the data, ignore entries
        // that fall within the min and max for that day.
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
}