<?php

namespace Sequra\Core\Services\Infrastructure;

use SeQura\Core\Infrastructure\Logger\LogData;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter as ShopLoggerAdapterInterface;

/**
 * Class ShopLoggerAdapter.
 *
 * @package Sequra\Core\Services\Infrastructure
 */
class ShopLoggerAdapter implements ShopLoggerAdapterInterface
{
    /**
     * @inheritDoc
     */
    public function logMessage(LogData $data): void
    {
    }
}
