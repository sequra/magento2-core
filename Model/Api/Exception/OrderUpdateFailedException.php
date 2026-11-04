<?php

namespace Sequra\Core\Model\Api\Exception;

use RuntimeException;

/**
 * An order update seQura or integration-core did not accept.
 *
 * The message carries the error of the integration-core response, prefixed with its error code when it has one.
 */
class OrderUpdateFailedException extends RuntimeException
{
    /**
     * @param string $message
     * @param string $errorCode
     */
    public function __construct(string $message, string $errorCode = '')
    {
        parent::__construct($errorCode !== '' ? "[$errorCode] $message" : $message);
    }
}
