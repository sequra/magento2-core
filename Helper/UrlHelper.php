<?php

namespace Sequra\Core\Helper;

use Magento\Backend\Model\UrlInterface as MagentoBackendUrl;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Url as MagentoUrl;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\UrlInterface;
use SeQura\Core\BusinessLogic\SeQuraAPI\BaseProxy;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\Infrastructure\ServiceRegister;

class UrlHelper
{
    public const SEQURA_PORTAL_SANDBOX_URL = 'https://simbox.sequrapi.com/orders/';
    public const SEQURA_PORTAL_URL = 'https://simba.sequra.com/orders/';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var MagentoUrl
     */
    private $urlHelper;
    /**
     * @var MagentoBackendUrl
     */
    private $backendUrlHelper;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * UrlHelper constructor.
     *
     * @param StoreManagerInterface $storeManager Store manager
     * @param MagentoUrl $urlHelper URL helper
     * @param MagentoBackendUrl $backendUrlHelper Backend URL helper
     * @param OrderFactory $orderFactory Order factory
     * @param UrlInterface $urlBuilder URL builder
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        MagentoUrl            $urlHelper,
        MagentoBackendUrl     $backendUrlHelper,
        OrderFactory          $orderFactory,
        UrlInterface          $urlBuilder
    ) {
        $this->storeManager = $storeManager;
        $this->urlHelper = $urlHelper;
        $this->backendUrlHelper = $backendUrlHelper;
        $this->orderFactory = $orderFactory;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Returns front-end controller URL.
     *
     * @param string $routePath Path.
     * @param array|null $routeParams Parameters.
     * @phpstan-param array<string, mixed>|null $routeParams
     *
     * @return string Publicly visible URL of the requested front-end controller.
     *
     * @throws NoSuchEntityException
     */
    public function getFrontendUrl(string $routePath, array $routeParams = null): string
    {
        $storeView = $this->storeManager->getStore();
        $url = $this->urlHelper->setScope($storeView)->getUrl($routePath, $routeParams);

        if ($routeParams !== null) {
            return $url;
        }

        return explode('?', $url)[0];
    }

    /**
     * Returns back-end controller URL.
     *
     * @param string $routePath Path.
     * @param array|null $routeParams Parameters.
     * @phpstan-param array<string, mixed>|null $routeParams
     *
     * @return string Publicly visible URL of the requested back-end controller.
     */
    public function getBackendUrl(string $routePath, array $routeParams = null): string
    {
        return $this->backendUrlHelper->getUrl($routePath, $routeParams);
    }

    /**
     * Returns the URL for the Sequra order in the backend.
     *
     * @param string $orderReference The order reference.
     */
    public function getBackendUrlForSequraOrder(string $orderReference): string
    {
        $storeId = $this->getOrderStoreId($orderReference);
        if (!$storeId) {
            return '#';
        }
        /**
         * @var \SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData|null $connectionSettings
         */
        $connectionSettings = StoreContext::doWithStore(
            (string) $storeId,
            function () {
                return ServiceRegister::getService(ConnectionService::class)->getConnectionData();
            }
        );
        $baseUrl = $connectionSettings && $connectionSettings->getEnvironment() === BaseProxy::LIVE_MODE ?
            self::SEQURA_PORTAL_URL : self::SEQURA_PORTAL_SANDBOX_URL;
        return $this->urlBuilder->getUrl($baseUrl . $orderReference);
    }

    /**
     * Returns the store id by order reference.
     *
     * @param string $orderReference
     *
     * @return int|null
     */
    private function getOrderStoreId($orderReference): ?int
    {
        $order = $this->orderFactory->create();
        $seQuraOrder = $this->getOrderRepository()->getByOrderReference($orderReference);
        if (!$seQuraOrder) {
            return null;
        }
        $order->loadByIncrementId($seQuraOrder->getOrderRef1());
        return $order ? $order->getStoreId() : null;
    }

    /**
     * Returns an instance of Order service.
     *
     * @return SeQuraOrderRepositoryInterface
     */
    private function getOrderRepository(): SeQuraOrderRepositoryInterface
    {
        return ServiceRegister::getService(SeQuraOrderRepositoryInterface::class);
    }
}
