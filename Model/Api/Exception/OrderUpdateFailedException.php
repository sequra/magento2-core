<?php

namespace Sequra\Core\Model\Api\Exception;

use RuntimeException;

/**
 * An order update seQura or integration-core did not accept.
 *
 * The integration-core Admin API logs the original exception and answers with an error response, so this keeps
 * what that response tells apart: the translatable error code and the HTTP status code.
 */
class OrderUpdateFailedException extends RuntimeException
{
    /**
     * @var string
     */
    private $errorCode;

    /**
     * @param string $message
     * @param string $errorCode
     * @param int $statusCode
     */
    public function __construct(string $message, string $errorCode, int $statusCode)
    {
        parent::__construct($errorCode !== '' ? "[$errorCode] $message" : $message, $statusCode);

        $this->errorCode = $errorCode;
    }

    /**
     * Returns the integration-core error code, e.g. general.errors.order.notFound.
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
