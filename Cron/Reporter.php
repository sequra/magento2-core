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
    protected $config;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Sequra\Core\Model\ReporterFactory
     */
    protected $reporter;


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
        $this->config = $configFactory->create();
        $this->reporter = $reporterFactory->create();
        $this->logger = $logger;
    }

    /**
     * Send delivery report to SeQura
     *
     * @return $this
     */
    public function execute()
    {
        if (!$this->config->getCoreValue('reporting')) {
            return;
        }

        if ($this->config->getCoreValue('reportingtime') == date('H')) {
            $this->reporter->sendOrderWithShipment();
            $this->logger->info("SEQURA: report sent");
        } else {
            $this->logger->info(date('H') . "SEQURA: It isn't time to send the report, it is programmed at 0" . $this->config->getCoreValue('reportingtime') . ':00 AM');
        }
    }

}


