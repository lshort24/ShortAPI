<?php

namespace ShortAPI\GraphQL\Data;

use Exception;
use GraphQL\Error\ClientAware;

class GraphQLException extends Exception implements ClientAware
{

    public function isClientSafe() : bool
    {
        return true;
    }

    public function getCategory() : string
    {
        return 'GraphQL API Error';
    }
}