<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model;

class AbstractIpn
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * IPN request data
     *
     * @var array
     */
    protected $ipnRequest;

    /**
     * Collected debug information
     *
     * @var array
     */
    protected $debugData = [];

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->ipnRequest = $data;
    }

    /**
     * IPN request data getter
     *
     * @param string $key
     * @return array|string
     */
    public function getRequestData($key = null, $default = null)
    {
        if (null === $key) {
            return $this->ipnRequest;
        }
        return isset($this->ipnRequest[$key]) ? $this->ipnRequest[$key] : $default;
    }

    /**
     * @param string $key
     * @param array|string $value
     * @return $this
     */
    protected function addDebugData($key, $value)
    {
        $this->debugData[$key] = $value;
        return $this;
    }

    /**
     * Log debug data to file
     *
     * @return void
     */
    protected function debug()
    {
        if ($this->config && $this->config->getValue('debug')) {
            $this->logger->debug(var_export($this->debugData, true));
        }
    }
}
