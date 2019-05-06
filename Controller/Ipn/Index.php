<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Controller\Ipn;

/**
 * Unified IPN controller for all supported PayPal methods
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Sequra\Core\Model\IpnFactory
     */
    protected $_ipnFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Sequra\Core\Model\IpnFactory $ipnFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Sequra\Core\Model\IpnFactory $ipnFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger = $logger;
        $this->_ipnFactory = $ipnFactory;
        parent::__construct($context);
        // Fix for Magento2.3 adding isAjax to the request params
        if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
            $request = $this->getRequest();
            if ($request instanceof \Magento\Framework\App\Request\Http && $request->isPost()) {
                $request->setParam('isAjax', true);
            }
        }
    }

    /**
     * Instantiate IPN model and pass IPN request to it
     *
     * @return void
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }
        try {
            $data = $this->getRequest()->getPostValue();
            $this->_ipnFactory->create(['data' => $data])->processIpnRequest();
        } catch (\Exception $e) {
            $this->_logger->critical($e->getMessage());
            $this->getResponse()->setHttpResponseCode(500);
            $this->getResponse()->setBody($e->getMessage() . "\n" . $e->getTraceAsString());
            $this->getResponse()->sendResponse();
        }
    }
}
