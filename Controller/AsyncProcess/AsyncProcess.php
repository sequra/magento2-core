<?php

namespace Sequra\Core\Controller\AsyncProcess;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\Interfaces\AsyncProcessService;
use SeQura\Core\Infrastructure\Logger\LogContextData;

class AsyncProcess extends Action implements CsrfAwareActionInterface
{
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

    /**
     * Lets the default CSRF failure response stand.
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Exempts the async task-queue runner from form-key validation.
     *
     * It is invoked by integration-core out of band, with no browser session, and is authorised
     * by the guid it is called with.
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
