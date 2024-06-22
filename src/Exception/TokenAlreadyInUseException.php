<?php

namespace App\Exception;

class TokenAlreadyInUseException extends ApiException {

    function __construct($identifier) {
        parent::__construct(sprintf("Token '%s' is already in use by another user", $identifier), 409);
    }
}