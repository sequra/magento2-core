<?php

namespace Sequra\Core\Services\Infrastructure;

use SeQura\Core\Infrastructure\Logger\LogData;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter as ShopLoggerAdapterInterface;

/**
 * Class ShopLoggerAdapter.
 *
 * Delegates to DefaultLoggerAdapter so that shop-level log messages
 * are written to sequra_debug.log alongside all other SeQura logs.
 *
 * @package Sequra\Core\Services\Infrastructure
 */
class ShopLoggerAdapter implements ShopLoggerAdapterInterface
{
    /**
     * @var DefaultLoggerAdapter
     */
    private DefaultLoggerAdapter $defaultLoggerAdapter;

    /**
     * @param DefaultLoggerAdapter $defaultLoggerAdapter
     */
    public function __construct(DefaultLoggerAdapter $defaultLoggerAdapter)
    {
        $this->defaultLoggerAdapter = $defaultLoggerAdapter;
    }

    /**
     * @inheritDoc
     */
    public function logMessage(LogData $data): void
    {
        $this->defaultLoggerAdapter->logMessage($data);
    }
}
