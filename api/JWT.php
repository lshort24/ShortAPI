<?php
namespace ShortAPI;
require_once __DIR__ . '/config/secrets.php';

class JWT
{
    private static ?self $instance = null;

    public static function instance() : self {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
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

        // Verify signature
        [$base64Header, $base64Payload, $base64Signature] = explode('.', $token);
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secrets['authorizationSecret'], true);
        $signatureToCheck = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        if ($signatureToCheck == $base64Signature) {
            return json_decode(base64_decode($base64Payload), true);
        }
        return [];
    }
}