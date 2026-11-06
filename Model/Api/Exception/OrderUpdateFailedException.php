<?php

namespace Sequra\Core\Model\Api\Exception;

use RuntimeException;

/**
 * An order update seQura or integration-core did not accept.
 *
 * The message carries the error of the integration-core response, prefixed with its error code when it has one.
 * The whole response is kept, so a caller can tell a refused update from a failed call. Integration-core has
 * already logged the original exception with its trace.
 */
class OrderUpdateFailedException extends RuntimeException
{
    /**
     * @var mixed[]
     */
    private $errorDetails;

    /**
     * @param string $message
     * @param string $errorCode
     * @param mixed[] $errorDetails The error response of integration-core, as its toArray() returns it.
     */
    public function __construct(string $message, string $errorCode = '', array $errorDetails = [])
    {
        parent::__construct($errorCode !== '' ? "[$errorCode] $message" : $message);
        $this->errorDetails = $errorDetails;
    }

    /**
     * Returns the error response of integration-core, as its toArray() returns it.
     *
     * @return mixed[]
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }
}
