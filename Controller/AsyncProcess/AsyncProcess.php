<?php

namespace Sequra\Core\Controller\AsyncProcess;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\Interfaces\AsyncProcessService;
use SeQura\Core\Infrastructure\Logger\LogContextData;
use Sequra\Core\Controller\CsrfExemptTrait;

/**
 * Form-key validation is skipped ({@see CsrfExemptTrait}): integration-core invokes this out of
 * band with no browser session, and the guid it is called with is what authorises it.
 */
class AsyncProcess extends Action implements CsrfAwareActionInterface
{
    use CsrfExemptTrait;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var AsyncProcessService
     */
    private $asyncProcessService;

    /**
     * AsyncProcess constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(Context $context, JsonFactory $resultJsonFactory)
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Execute action based on request and return result.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        /**
         * @var string $guid
         */
        $guid = $this->_request->getParam('guid');
        Logger::logInfo('Received async process request.', 'Integration', [new LogContextData('guid', $guid)]);

        $this->getAsyncProcessService()->runProcess($guid);

        return $this->resultJsonFactory->create(['success' => true]);
    }

    /**
     * Get the AsyncProcessService instance.
     *
     * @return AsyncProcessService
     */
    private function getAsyncProcessService(): AsyncProcessService
    {
        if ($this->asyncProcessService === null) {
            $this->asyncProcessService = ServiceRegister::getService(AsyncProcessService::CLASS_NAME);
        }

        return $this->asyncProcessService;
    }
}
