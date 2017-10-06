<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Cron;

use Sequra\Core\Model\ConfigFactory;

class Reporter
{
    /**
     * @var \Sequra\Core\Model\Config
     */
    protected $_config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Sequra\Core\Model\ReporterFactory
     */
    protected $_reporter;


    /**
     * Constructor
     *
     * @param ConfigFactory $configFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Sequra\Core\Model\ReporterFactory $reporterFactory
     */
    public function __construct(
        ConfigFactory $configFactory,
        \Psr\Log\LoggerInterface $logger,
        \Sequra\Core\Model\ReporterFactory $reporterFactory
    ) {
        $this->_config = $configFactory->create();
        $this->_reporter = $reporterFactory->crete();
        $this->_logger = $logger;
    }

    /**
     * Send delivery report to SeQura
     *
     * @return $this
     */
    public function execute()
    {
        if (!$this->_config->getCoreValue('reporting')) {
            return;
        }

        if ($this->_config->getCoreValue('reportingtime') == date('H')) {
            $this->_reporter->sendOrderWithShipment();
            $this->_logger->info("SEQURA: report sent");
        } else {
            $this->_logger->info(date('H') . "SEQURA: It isn't time to send the report, it is programmed at 0" . $this->_config->getCoreValue('reportingtime') . ':00 AM');
        }
    }

}