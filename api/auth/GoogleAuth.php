<?php
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/../config/Database.php';

class GoogleAuth
{
    function authenticate(string $bearerToken) : Auth {
        if (empty($bearerToken)) {
            return new Auth(false, 'No bearer token was specified.');
        }

        $secrets = getSecrets();

        try {
            $client = new Google_Client(['client_id' => $secrets['googleClientId']]);
            $payload = $client->verifyIdToken($bearerToken);
        }
        catch (Throwable $e) {
            return new Auth(false, $e->getMessage());
        }

        if (empty($payload)) {
            return new Auth(false, 'Invalid ID Token.');
        }

        $userId = $payload['sub'];

        // Look the user up in our database
        $query = "
        SELECT *
        FROM users
        WHERE 
            user_id = :userId AND 
            user_type = 'google'
        ";

        $params = [
            ':userId' => [
                "value" => $userId,
                "type" => PDO::PARAM_STR
            ]
        ];

        $database = new Database();
        $conn = $database->getConnection();
        $stmt = $conn->prepare($query);
        foreach ($params as $name => $param) {
            $stmt->bindParam($name, $param["value"], $param["type"]);
        }

        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            return new Auth(false, 'Could not find user in the database.');
        }
        return new Auth(true, '');
    }
}