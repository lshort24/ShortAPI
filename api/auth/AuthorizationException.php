<?php
namespace ShortAPI\auth;

use Exception;
use GraphQL\Error\ClientAware;

class AuthorizationException extends Exception implements ClientAware {
    const PERMISSION_DENIED = 100;
    const INVALID_ACCESS_TOKEN = 101;
    const ACCESS_TOKEN_EXPIRED = 102;

    public function isClientSafe() : bool
    {
        return true;
    }

    public function getCategory() : string
    {
        return "auth_$this->code";
    }
}