<?php

namespace ShortAPI\services;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PDO;
use ShortAPI\auth\Authorization;
use ShortAPI\config\Database;
use Throwable;

class UserService
{
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
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../app.log', Logger::DEBUG));
    }


    /**
     * @param string $userId
     * @param bool $asAdmin - true means we are calling this function internally
     * @return array
     * @throws DatabaseException
     */
    public function getUserByUserId(string $userId, bool $asAdmin = false) : array {
        if (!$asAdmin && !Authorization::instance()->hasRole(Authorization::ADMIN_ROLE)) {
            $this->log->error("Permission denied.");
            throw new DatabaseException("Permission denied.");
        }

        if (empty($userId)) {
            $this->log->debug('No user id was specified.');
            throw new DatabaseException("Could not access user.");
        }

        $fields = 'user_id, role';
        $params = [
            'user_id' => $userId
        ];
        $sql = "SELECT $fields FROM users WHERE user_id = :user_id";
        try {
            $pdo = $this->database->getConnection('goodfood', Authorization::ADMIN_ROLE);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        catch (Throwable $ex) {
            $this->log->debug("Could not fetch user with use id $userId.");
            throw new DatabaseException("Could not access user.");
        }

        $records = $stmt->fetchAll();
        if (empty($records)) {
            $this->log->debug("User with user id $userId was not found.");
            throw new DatabaseException("The user you requested was not found.");
        }
        return $records[0];
    }
}