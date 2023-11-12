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

    const UPDATE_ACCESS_TOKEN_SQL = <<< SQL
        UPDATE users
        SET 
            accessToken = :access_token,
            lastActivity = NOW()
        WHERE id = :user_id
SQL;

    const DELETE_ACCESS_TOKEN_SQL = <<< SQL
        UPDATE users
        SET 
            accessToken = '',
            lastActivity = NOW()
        WHERE id = :user_id
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


    /**
     * Update access token for a give user
     *
     * @param int $userId
     * @param string $accessToken
     * @return void
     * @throws DatabaseException
     */
    public function updateAccessToken(int $userId, string $accessToken) : void {
        // Validate arguments
        if ($userId <= 0) {
            $message = 'Invalid user id.';
            $this->log->error(self::SERVICE_NAME . ":$message", ['userId' => $userId]);
            throw new DatabaseException($message);
        }
        if (empty($accessToken)) {
            $message = 'No access token was specified.';
            $this->log->error(self::SERVICE_NAME . ":$message", ['userId' => $userId]);
            throw new DatabaseException($message);
        }

        try {
            $pdo = $this->database->getConnection('sugar_curves', Authorization::USER_ROLE);
        }
        catch (Throwable $ex) {
            $this->log->error(self::SERVICE_NAME . ": Could not create database connection.", ['ex' => $ex->getMessage()]);
            throw new DatabaseException('Could not create database connection.');
        }

        $statement = $pdo->prepare(self::UPDATE_ACCESS_TOKEN_SQL);
        $params = [
            'user_id' => $userId,
            'access_token' => $accessToken
        ];
        try {
            $statement->execute($params);
        }
        catch (Throwable $ex) {
            $message = 'Database error updating user account.';
            $this->log->error(self::SERVICE_NAME . ":$message", ['ex' => $ex->getMessage(), 'userId' => $userId]);
            throw new DatabaseException($message);
        }
        if ($statement->rowCount() === 0) {
            $message = 'Update to user did not modify any rows.';
            $this->log->error(self::SERVICE_NAME . ":$message", ['userId' => $userId]);
            throw new DatabaseException($message);
        }
    }


    /**
     * Remove access token for a give user
     *
     * @param int $userId
     * @return void
     * @throws DatabaseException
     */
    public function removeAccessToken(int $userId) : void {
        // Validate arguments
        if ($userId <= 0) {
            $message = 'Invalid user id.';
            $this->log->error(self::SERVICE_NAME . ":$message", ['userId' => $userId]);
            throw new DatabaseException($message);
        }

        try {
            $pdo = $this->database->getConnection('sugar_curves', Authorization::USER_ROLE);
        }
        catch (Throwable $ex) {
            $this->log->error(self::SERVICE_NAME . ": Could not create database connection.", ['ex' => $ex->getMessage()]);
            throw new DatabaseException('Could not create database connection.');
        }

        $statement = $pdo->prepare(self::DELETE_ACCESS_TOKEN_SQL);
        $params = [
            'user_id' => $userId
        ];
        try {
            $statement->execute($params);
        }
        catch (Throwable $ex) {
            $message = 'Database error updating user account.';
            $this->log->error(self::SERVICE_NAME . ":$message", ['ex' => $ex->getMessage(), 'userId' => $userId]);
            throw new DatabaseException($message);
        }
        if ($statement->rowCount() === 0) {
            $message = 'Update to user did not modify any rows.';
            $this->log->error(self::SERVICE_NAME . ":$message", ['userId' => $userId]);
            throw new DatabaseException($message);
        }
    }
}