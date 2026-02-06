<?php

namespace Sequra\Core\Controller\IntegrationWebhook;

use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\ConfigurationWebhookAPI;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;

class Index implements HttpPostActionInterface
{
    /**
     * @var HttpRequest
     */
    private HttpRequest $request;
    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @param HttpRequest $request
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        HttpRequest $request,
        JsonFactory $jsonFactory
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
    }

    /**
     * Execute webhook endpoint
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        $payload = json_decode($this->request->getContent(), true) ?? [];

        $storeId = (string)$this->request->getParam('storeId', '');
        $merchant = $this->request->getParam('merchantId');
        $signature = $this->request->getParam('signature');

        if (!$storeId) {
            return $result
                ->setHttpResponseCode(400)
                ->setData(['error' => 'Missing storeId']);
        }

        $response = ConfigurationWebhookAPI::configurationHandler($storeId)
            ->handleRequest($merchant, $signature, $payload);

        $responseArray = $response->toArray();

        if ($response->isSuccessful()) {
            return $result
                ->setHttpResponseCode(200)
                ->setData($responseArray);
        }

        $code = ($responseArray['errorCode'] ?? null) === 409 ? 409 : 410;

        return $result
            ->setHttpResponseCode($code)
            ->setData([
                'error' => $responseArray['errorMessage'] ?? 'Unknown error'
            ]);
    }
}
