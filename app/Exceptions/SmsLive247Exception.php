<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by SmsLive247Service when a call fails: missing configuration, a connection
 * error, or a non-2xx response. The message is SMSLive247's own error text when the
 * response carries one. $status is the HTTP status (null for connection errors).
 */
class SmsLive247Exception extends Exception
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
