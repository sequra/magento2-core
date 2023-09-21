<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Webapi\Exception;
use SeQura\Core\BusinessLogic\AdminAPI\Response\Response;

/**
 * Class BaseConfigurationController
 *
 * @package Sequra\Core\Controller\Adminhtml\Configuration
 */
class BaseConfigurationController extends Action
{
    /**
     * Actions that are being handled by controllers.
     *
     * @var array
     */
    protected $allowedActions = [];
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var Json
     */
    protected $result;

    /**
     * @var string
     */
    protected $storeId;

    /**
     * @var string|null
     */
    protected $identifier;

    /**
     * Configuration constructor.
     *
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(Context $context, JsonFactory $jsonFactory)
    {
        parent::__construct($context);

        $this->resultJsonFactory = $jsonFactory;
        $this->result = $this->resultJsonFactory->create();
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $request = $this->getRequest();

        if (!$request->getParam('action')
            || !method_exists($this, $request->getParam('action'))
            || !in_array($request->getParam('action'), $this->allowedActions, true)
        ) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(
                [
                    'success' => false,
                    'message' => _('Wrong action requested.'),
                ]
            );
        }

        $this->storeId = $request->getParam('storeId');
        $this->identifier = $request->getParam('identifier');
        $action = $request->getParam('action');

        return $this->$action();
    }

    /**
     * Returns post data from Sequra request.
     *
     * @return array
     */
    protected function getSequraPostData(): array
    {
        return json_decode(file_get_contents('php://input'), true);
    }

    /**
     * Adds a response code to the result.
     *
     * @param Response $response
     *
     * @return void
     */
    protected function addResponseCode(Response $response): void
    {
        $this->result->setHttpResponseCode($response->isSuccessful() ? 200 : $response->toArray()['errorCode']);
    }
}
