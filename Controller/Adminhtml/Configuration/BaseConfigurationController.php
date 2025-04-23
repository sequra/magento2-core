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

class BaseConfigurationController extends Action
{
    /**
     * Actions that are being handled by controllers.
     *
     * @var string[]
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
        /**
         * @var string $action
         */
        $action = $request->getParam('action');
        if (!$action || !method_exists($this, $action) || !in_array($action, $this->allowedActions, true)) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(
                [
                    'success' => false,
                    // TODO: The use of function _() is discouraged; use AdapterInterface::translate() instead
                    // phpcs:ignore Magento2.Functions.DiscouragedFunction.DiscouragedWithAlternative
                    'message' => _('Wrong action requested.'),
                ]
            );
        }

        /**
         * @var string $storeId
         */
        $storeId = $request->getParam('storeId');
        $this->storeId = $storeId;

        /**
         * @var string $identifier
         */
        $identifier = $request->getParam('identifier');
        $this->identifier = $identifier;

        return $this->$action();
    }

    /**
     * Returns post data from Sequra request.
     *
     * @return mixed[]
     */
    protected function getSequraPostData(): array
    {
        // TODO: The use of function file_get_contents() is discouraged
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $json = json_decode((string) file_get_contents('php://input'), true);
        return is_array($json) ? $json : [];
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
        $statusCode = 200;
        if (!$response->isSuccessful()) {
            $arr = $response->toArray();
            $statusCode = isset($arr['statusCode']) && is_numeric($arr['statusCode']) ? (int) $arr['statusCode'] : 500;
        }
        $this->result->setHttpResponseCode($statusCode);
    }
}
