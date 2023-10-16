<?php

namespace ShortAPI\SugarCurves\services;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PDO;
use ShortAPI\auth\Authorization;
use ShortAPI\config\Database;
use ShortAPI\services\DatabaseException;
use Throwable;

class UserService
{
    const SERVICE_NAME = 'User Service';
    const GET_USER_BY_GOOGLE_ID_SQL = <<< SQL
        SELECT * FROM users WHERE googleId = :google_id LIMIT 1
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
     * Find user by google id
     *
     * @param string $googleId
     * @return array|null
     * @throws DatabaseException
     */
    public function find_user_by_google_id(string $googleId) : ?array {
        try {
            $pdo = $this->database->getConnection('sugar_curves', Authorization::USER_ROLE);
        }
        catch (Throwable $ex) {
            $this->log->error(self::SERVICE_NAME . ": Could not create database connection.", ['ex' => $ex->getMessage()]);
            throw new DatabaseException('Could not create database connection.');
        }

        $statement = $pdo->prepare(self::GET_USER_BY_GOOGLE_ID_SQL);
        $params = [
            'google_id' => $googleId
        ];
        try {
            $statement->execute($params);
        }
        catch (Throwable $ex) {
            $message = 'Database error fetching user account.';
            $this->log->error(self::SERVICE_NAME . ":$message", ['ex' => $ex->getMessage(), 'googleId' => $googleId]);
            throw new DatabaseException($message);
        }
        if ($statement->rowCount() === 0) {
            return null;
        }
        return $statement->fetch(PDO::FETCH_ASSOC);
    }
}