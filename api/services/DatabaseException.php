<?php

namespace ShortAPI\services;

use Exception;
use GraphQL\Error\ClientAware;

class DatabaseException extends Exception implements ClientAware
{

    public function isClientSafe() : bool
    {
        return true;
    }

    public function getCategory() : string
    {
        return 'Database Error';
    }
}