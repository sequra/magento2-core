<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Controller\Webhook;

/**
 * Unified Webhook controller for all supported PayPal methods
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Sequra\Core\Model\WebhookFactory
     */
    protected $_WebhookFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Sequra\Core\Model\WebhookFactory $WebhookFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Sequra\Core\Model\WebhookFactory $WebhookFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger = $logger;
        $this->_WebhookFactory = $WebhookFactory;
        parent::__construct($context);
    }

    /**
     * Instantiate Webhook model and pass Webhook request to it
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
            $this->_WebhookFactory->create(['data' => $data])->processWebhookRequest();
        } catch (\Exception $e) {
            $this->_logger->critical($e->getMessage());
            $this->getResponse()->setHttpResponseCode(500);
            $this->getResponse()->setBody($e->getMessage() . "\n" . $e->getTraceAsString());
            $this->getResponse()->sendResponse();
        }
    }
}
