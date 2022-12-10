<?php
namespace ShortAPI\config;

use Exception;
use PDO;

require_once 'secrets.php';

class Database {
    /**
     * Get the database connection
     *
     * @param string $db_name
     * @return PDO
     */
    public function getConnection(string $db_name) : PDO
    {
        $secrets = getSecrets();
        $host = $secrets['db_host'];
        $username = $secrets['username']['user'];

        try {
            $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $secrets['db_password']);
            $conn->exec("set names utf8");
        }
        catch(Exception $exception){
            die("Connection error: " . $exception->getMessage());
        }

        return $conn;
    }
}