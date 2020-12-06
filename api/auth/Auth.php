<?php


class Auth
{
    public $isAuthorized;
    public $reason;

    function __construct(bool $isAuthorized, string $reason) {
        $this->isAuthorized = $isAuthorized;
        $this->reason = $reason;
    }
}