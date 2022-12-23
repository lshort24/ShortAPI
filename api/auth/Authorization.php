<?php
namespace ShortAPI\auth;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use ShortAPI\JWT;
use ShortAPI\services\DatabaseException;
use ShortAPI\services\UserService;

class Authorization
{
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


    public function setToken(string $token) : void {
        if (empty($token)) {
            $this->log->error("No token was specified.");
            return;
        }

        if ($this->token !== null) {
            $this->log->error("Token is already set.");
            return;
        }
        $this->token = $token;
    }


    /**
     * @param string $role
     * @return bool
     * @throws DatabaseException
     */
    public function hasRole(string $role) : bool {
        if (in_array($role, $this->roles)) {
            return true;
        }

        if (!in_array($role, [self::GUEST_ROLE, self::USER_ROLE, self::ADMIN_ROLE])) {
            $this->log->error("Invalid role $role.");
            return false;
        }

        if (empty($this->token)) {
            $this->log->error("User has no role because the token was empty.");
            return false;
        }

        // Verify the token
        $payload = JWT::instance()->decode($this->token);
        if (empty($payload)) {
            $this->log->error("Could not decode the authorization token.");
            return false;
        }

        if (empty($payload['user_id'])) {
            $this->log->error("No user id was specified in the authorization token.");
            return false;
        }

        $user = UserService::instance()->getUserByUserId($payload['user_id'], 'google', true);

        if ($user['role'] === Authorization::USER_ROLE) {
            $this->roles = [Authorization::GUEST_ROLE, Authorization::USER_ROLE];
        }
        else if ($user['role'] === Authorization::ADMIN_ROLE) {
            $this->roles = [Authorization::GUEST_ROLE, Authorization::USER_ROLE, Authorization::ADMIN_ROLE];
        }

        if (in_array($role, $this->roles)) {
            return true;
        }

        $this->log->error("Requesting role $role, but the user has the role {$user['role']}.");
        return false;
    }
}