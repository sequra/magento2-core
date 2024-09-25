<?php

namespace Sequra\Core\Ui\Component\Listing\Column;

use Magento\Framework\View\Asset\Repository;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\UrlInterface;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\OrderRequestStates;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\BusinessLogic\SeQuraAPI\BaseProxy;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

/**
 * Class SequraOrderLink
 *
 * @package Sequra\Core\Ui\Component\Listing\Column
 */
class SequraOrderLink extends Column
{
    protected $assetRepository;
    protected $urlBuilder;
    protected $translationProvider;
    public const SEQURA_PORTAL_SANDBOX_URL = 'https://simbox.sequrapi.com/orders/';
    public const SEQURA_PORTAL_URL = 'https://simba.sequra.com/orders/';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Repository $assetRepository
     * @param UrlInterface $urlBuilder
     * @param SeQuraTranslationProvider $translationProvider
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface          $context,
        UiComponentFactory        $uiComponentFactory,
        Repository                $assetRepository,
        UrlInterface              $urlBuilder,
        SeQuraTranslationProvider $translationProvider,
        array                     $components = [],
        array                     $data = []
    )
    {
        $this->assetRepository = $assetRepository;
        $this->urlBuilder = $urlBuilder;
        $this->translationProvider = $translationProvider;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Fills in data in the orders sequra columns.
     *
     * @param array $dataSource
     *
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $shopOrderReferences = [];
        foreach ($dataSource['data']['items'] as $item) {
            if (isset($item['entity_id'])) {
                $shopOrderReferences[] = $item['increment_id'];
            }
        }
        if (empty($shopOrderReferences)) {
            return $dataSource;
        }
        $orders = $this->getOrderService()->getOrderBatchForShopReferences($shopOrderReferences);
        $referenceMap = $this->createReferenceMap($orders);

        foreach ($dataSource['data']['items'] as & $item) {
            if (isset($item['entity_id'], $referenceMap[$item['increment_id']])) {
                $orderInfo = $referenceMap[$item['increment_id']];
                $item[$this->getData('name') . '_reference'] = $orderInfo['ref'];
                $orderInfo['isApproved'] && $item[$this->getData('name')] = $this->getButtonLink($orderInfo['ref']);
            }
        }

        return $dataSource;
    }

    /**
     * Creates an order reference map with sequra order reference and shop order reference.
     *
     * @param SeQuraOrder[] $orders
     * @return array
     */
    private function createReferenceMap(array $orders): array
    {
        $refMap = [];
        foreach ($orders as $order) {
            $refMap[$order->getOrderRef1()] = [
                'ref' => $order->getReference(),
                'isApproved' => $order->getState() === OrderRequestStates::CONFIRMED
            ];
        }

        return $refMap;
    }

    /**
     * Generates a button link html for a provided order reference.
     *
     * @param string $orderReference
     *
     * @return string
     */
    private function getButtonLink(string $orderReference): string
    {
        $imagePath = $this->assetRepository->getUrl('Sequra_Core::images/sequra-logo.png');

        return html_entity_decode(
            '<a class="sequra-link" href="' . $this->getButtonLinkUrl($orderReference) . '" target="_blank" onclick="event.stopPropagation()">
                        <button class="sequra-preview">
                            <img class="sequra-logo" src=' . $imagePath . ' alt="sequra-logo">
                                ' . $this->translationProvider->translate("sequra.viewOnSequra") . '
                        </button>
                   </a>');
    }

    private function getButtonLinkUrl(string $orderReference): string
    {
        $connectionSettings = $this->getConnectionSettings();
        $baseUrl = $connectionSettings && $connectionSettings->getEnvironment() === BaseProxy::LIVE_MODE ?
            self::SEQURA_PORTAL_URL : self::SEQURA_PORTAL_SANDBOX_URL;
        return $this->urlBuilder->getUrl( $baseUrl . $orderReference );
    }

    /**
     * Returns an instance of Order service.
     *
     * @return OrderService
     */
    private function getOrderService(): OrderService
    {
        if (!isset($this->orderService)) {
            $this->orderService = ServiceRegister::getService(OrderService::class);
        }

        return $this->orderService;
    }

    /**
     * @return ConnectionData|null
     */
    private function getConnectionSettings(): ?ConnectionData
    {
        return $this->getConnectionService()->getConnectionData();
    }

    /**
     * @return ConnectionService
     */
    private function getConnectionService(): ConnectionService
    {
        return ServiceRegister::getService(ConnectionService::class);
    }
}
