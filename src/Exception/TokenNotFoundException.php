<?php

namespace App\Exception;

class TokenNotFoundException extends ApiException
{

    function __construct($identifier)
    {
        parent::__construct(sprintf("No user with token '%s' found", $identifier), 404);
    }
}
