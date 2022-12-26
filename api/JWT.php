<?php
namespace ShortAPI;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once __DIR__ . '/config/secrets.php';

class JWT
{
    private static ?self $instance = null;
    private Logger $log;

    public static function instance() : self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../app.log', Logger::DEBUG));
    }


    public function encode(array $data) : string {
        $secrets = getSecrets();
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($data);
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secrets['authorizationSecret'], true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }


    public function decode(string $token) : array {
        $secrets = getSecrets();

        if (empty($token)) {
            $this->log->debug("Could not decode JWT because it was empty.");
            return [];
        }

        // Verify signature
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            $this->log->error("JWT does not have the required sections.");
            return [];
        }

        $base64Header = $parts[0];
        $base64Payload = $parts[1];
        $base64Signature = $parts[2];

        if (!strlen($base64Signature) > 0 || !strlen($base64Payload) || !strlen($base64Signature) > 0) {
            $this->log->error("JWT has empty sections.");
            return [];
        }

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secrets['authorizationSecret'], true);
        $signatureToCheck = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        if ($signatureToCheck == $base64Signature) {
            return json_decode(base64_decode($base64Payload), true);
        }

        $this->log->error("Signatures do not match.");
        return [];
    }
}