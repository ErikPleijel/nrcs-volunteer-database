<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by PaystackService when a Paystack API call fails: a non-2xx HTTP
 * response, or a 2xx response whose body carries {"status": false}. The
 * message is Paystack's own "message" field, not a generic HTTP error.
 */
class PaystackException extends Exception
{
}
