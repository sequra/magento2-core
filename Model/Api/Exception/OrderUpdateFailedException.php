<?php

namespace Sequra\Core\Model\Api\Exception;

use RuntimeException;

/**
 * An order update seQura or integration-core did not accept.
 *
 * The integration-core Admin API logs the original exception and answers with an error response, so this keeps
 * what that response tells apart: the translatable error code, in the message, and the HTTP status code.
 */
class OrderUpdateFailedException extends RuntimeException
{
    /**
     * @param string $message
     * @param string $errorCode
     * @param int $statusCode
     */
    public function __construct(string $message, string $errorCode, int $statusCode)
    {
        parent::__construct($errorCode !== '' ? "[$errorCode] $message" : $message, $statusCode);
    }
}
