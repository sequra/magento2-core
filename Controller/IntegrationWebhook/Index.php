<?php

namespace Sequra\Core\Controller\IntegrationWebhook;

use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\ConfigurationWebhookAPI;
use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Handlers\Enums\Topics;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Sequra\Core\Block\Banner;
use Zend_Cache;

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
     * @var PageCache
     */
    private PageCache $pageCache;

    /**
     * @param HttpRequest $request
     * @param JsonFactory $jsonFactory
     * @param PageCache $pageCache
     */
    public function __construct(
        HttpRequest $request,
        JsonFactory $jsonFactory,
        PageCache $pageCache
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->pageCache = $pageCache;
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

        $param = $this->request->getParam('storeId', '');

        if (!is_string($param) && !is_int($param)) {
            $param = '';
        }

        $storeId = (string)$param;
        $signature = $this->request->getParam('signature');

        if (!$storeId) {
            return $result
                ->setHttpResponseCode(400)
                ->setData(['error' => 'Missing storeId']);
        }

        $response = ConfigurationWebhookAPI::configurationHandler($storeId)
            ->handleRequest($signature, $payload);

        $responseArray = $response->toArray();

        if ($response->isSuccessful()) {
            $this->invalidateBannerFpcIfNeeded($payload, $storeId);

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

    /**
     * Invalidates Full Page Cache entries tagged with current store's banner identity
     *
     * The Block\Banner block exposes the cache tag `sequra_banner_<storeId>` through
     * its IdentityInterface implementation, and Magento attaches that tag to every
     * FPC entry that renders the block. Cleaning by this tag purges only the affected
     * pages and leaves unrelated cached content intact.
     *
     * @param $payload
     * @param string $storeId
     *
     * @return void
     */
    private function invalidateBannerFpcIfNeeded($payload, string $storeId): void
    {
        if (!is_array($payload)) {
            return;
        }

        $topic = $payload['topic'] ?? '';
        if ($topic !== Topics::SAVE_BANNER_SETTINGS) {
            return;
        }

        $this->pageCache->clean(
            Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG,
            [Banner::CACHE_TAG . '_' . $storeId]
        );
    }
}
