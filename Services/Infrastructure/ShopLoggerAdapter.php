<?php

namespace Sequra\Core\Services\Infrastructure;

use SeQura\Core\Infrastructure\Logger\LogData;
use SeQura\Core\Infrastructure\Logger\Interfaces\ShopLoggerAdapter as ShopLoggerAdapterInterface;

/**
 * Class ShopLoggerAdapter.
 *
 * Intentionally a no-op. Core's Logger::logMessage() calls this adapter on every
 * message but calls DefaultLoggerAdapter only when debug logging is enabled and
 * the message passes the configured level. Writing sequra_debug.log from here too
 * would bypass that gate — the Advanced tab's "enabled" flag and log level would
 * have no effect — and would duplicate every message that does pass it.
 *
 * @package Sequra\Core\Services\Infrastructure
 */
class ShopLoggerAdapter implements ShopLoggerAdapterInterface
{
    /**
     * @inheritDoc
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function logMessage(LogData $data): void
    {
    }
}
