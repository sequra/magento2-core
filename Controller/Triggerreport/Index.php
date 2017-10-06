<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Controller\Triggerreport;

use Magento\Framework\Exception\RemoteServiceUnavailableException;

/**
 * Unified IPN controller for all supported PayPal methods
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Sequra\Core\Cron\Reporter
     */
    protected $_reporter;

    /**
     * @param \Sequra\Core\Cron\Reporter $reporter
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Sequra\Core\Model\ReporterFactory $reporterFactory
    ) {
        $this->_reporter = $reporterFactory->create();
        parent::__construct($context);
    }

    /**
     * Instantiate IPN model and pass IPN request to it
     *
     * @return void
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function execute()
    {
        if($this->_reporter->sendOrderWithShipment()){
            die('ok');
        }
        die('ko');
    }
}
