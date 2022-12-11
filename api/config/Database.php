<?php
namespace ShortAPI\config;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\auth\Authorization;
use Exception;
use PDO;
use ShortAPI\services\DatabaseException;

require_once 'secrets.php';

class Database {
    private array $connections = [];
    private Logger $log;

    public function __construct() {
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../app.log', Logger::DEBUG));
    }

    /**
     * Get the database connection
     *
     * @param string $db_name
     * @param string $role
     * @return PDO
     * @throws DatabaseException
     */
    public function getConnection(string $db_name, string $role) : PDO
    {
        $secrets = getSecrets();
        $host = $secrets['db_host'];

        $connectionType = $role === Authorization::ADMIN_ROLE ? 'admin' : 'user';
        if (isset($this->connections[$connectionType])) {
            return $this->connections[$connectionType];
        }

        $username = $secrets['logins'][$connectionType]['username'];
        $password = $secrets['logins'][$connectionType]['password'];
        try {
            $this->connections[$connectionType] = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
            $this->connections[$connectionType]->exec("set names utf8");
        }
        catch(Exception $ex){
            $this->log->debug("Connection exception", ['error' => $ex]);
            throw new DatabaseException("Could not connect to the database");
        }
        return $this->connections[$connectionType];
    }
}