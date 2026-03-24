<?php
declare(strict_types=1);

namespace Sequra\Core\Model\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Debug extends Base
{
    /** @var string */
    protected $fileName = '/var/log/sequra_debug.log';

    /** @var int */
    protected $loggerType = Logger::DEBUG;
}
