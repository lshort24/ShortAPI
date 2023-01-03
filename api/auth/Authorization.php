<?php
namespace ShortAPI\auth;

use DateTime;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\JWT;
use ShortAPI\services\DatabaseException;
use ShortAPI\services\UserService;

class Authorization
{
    public const SERVICE_NAME = 'Authorization';

    public const GUEST_ROLE = 'guest';
    public const USER_ROLE = 'user';
    public const ADMIN_ROLE = 'admin';

    private static ?self $instance =  null;

    private ?string $token = null;
    private Logger $log;
    private array $roles;

    public static function instance() : self {
        if (static::$instance === null) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    public function __construct() {
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../../app.log', Logger::DEBUG));
        $this->roles = [self::GUEST_ROLE];
    }

    /**
     * @param string $token
     * @return void
     */
    public function setToken(string $token) : void {
        if (empty($token)) {
            $this->log->error(self::SERVICE_NAME . ': ' . "No token was specified.");
            return;
        }

        if ($this->token !== null) {
            $this->log->error(self::SERVICE_NAME . ': ' . "Token is already set.");
            return;
        }
        $this->token = $token;
    }


    /**
     * @param string $role
     * @return bool
     * @throws DatabaseException
     * @throws AuthorizationException
     */
    public function hasRole(string $role) : bool {
        if (in_array($role, $this->roles)) {
            return true;
        }

        if (!in_array($role, [self::GUEST_ROLE, self::USER_ROLE, self::ADMIN_ROLE])) {
            $this->log->error(self::SERVICE_NAME . ': ' . "Invalid role $role.");
            return false;
        }

        if (empty($this->token)) {
            $this->log->error(self::SERVICE_NAME . ': ' . "User has no role because the token was empty.");
            return false;
        }

        // Verify the token is signed and return the embedded data
        $payload = JWT::instance()->decode($this->token);
        if (empty($payload)) {
            $this->log->error(self::SERVICE_NAME . ': ' . "Could not decode the authorization token.");
            return false;
        }

        // Make sure the token is valid and has not expired
        $payload = JWT::instance()->decode($this->token);
        if (empty($payload) || empty($payload['expiresAt'])) {
            $this->log->error(self::SERVICE_NAME . ': ' . "Invalid access token", ['token' => $this->token]);
            throw new AuthorizationException("Permission denied.", AuthorizationException::INVALID_ACCESS_TOKEN);
        }

        $now = new DateTime();
        $expiresAt = new DateTime();
        $expiresAt->setTimeStamp($payload['expiresAt']);
        if ($expiresAt < $now) {
            $this->log->error(self::SERVICE_NAME . ': ' . "The access token has expired.");
            throw new AuthorizationException("Permission denied.", AuthorizationException::ACCESS_TOKEN_EXPIRED);
        }

        if (empty($payload['userId'])) {
            $this->log->error(self::SERVICE_NAME . ': ' . "No user id was specified in the authorization token.");
            return false;
        }

        $user = UserService::instance()->getUserByUserId($payload['userId'], 'google', true);

        if ($user['role'] === Authorization::USER_ROLE) {
            $this->roles = [Authorization::GUEST_ROLE, Authorization::USER_ROLE];
        }
        else if ($user['role'] === Authorization::ADMIN_ROLE) {
            $this->roles = [Authorization::GUEST_ROLE, Authorization::USER_ROLE, Authorization::ADMIN_ROLE];
        }

        if (in_array($role, $this->roles)) {
            return true;
        }

        $this->log->error(self::SERVICE_NAME . ': ' . "Requesting role $role, but the user has the role {$user['role']}.");
        return false;
    }
}