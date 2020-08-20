<?php
require_once 'secrets.php';

class Database {
    // specify your own database credentials
    private $host = "localhost";
    private $db_name = "timeline";
    private $username = "webuser";
    public $conn;

    /**
     * Get the database connection
     *
     * @return PDO
     */
    public function getConnection(){
        $this->conn = null;
        $secrets = getSecrets();

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $secrets['db_password']);
            $this->conn->exec("set names utf8");
        }
        catch(Exception $exception){
            die("Connection error: " . $exception->getMessage());
        }

        return $this->conn;
    }
}