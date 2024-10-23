<?php

namespace Sequra\Core\Ui\Component\Listing\Column;

use Magento\Framework\View\Asset\Repository;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\OrderRequestStates;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;
use Sequra\Core\Helper\UrlHelper;

/**
 * Class SequraOrderLink
 *
 * @package Sequra\Core\Ui\Component\Listing\Column
 */
class SequraOrderLink extends Column
{
    private $assetRepository;
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;
    /**
     * @var UrlHelper
     */
    private $urlHelper;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Repository $assetRepository
     * @param SeQuraTranslationProvider $translationProvider
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface          $context,
        UiComponentFactory        $uiComponentFactory,
        Repository                $assetRepository,
        SeQuraTranslationProvider $translationProvider,
        UrlHelper                 $urlHelper,
        array                     $components = [],
        array                     $data = []
    )
    {
        $this->assetRepository = $assetRepository;
        $this->translationProvider = $translationProvider;
        $this->urlHelper = $urlHelper;
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
                $orderInfo['isApproved'] && $item[$this->getData('name')] = $this->getButtonLink(
                    $this->urlHelper->getBackendUrlForSequraOrder($orderInfo['ref'])
                );
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
    private function getButtonLink(string $url): string
    {
        $imagePath = $this->assetRepository->getUrl('Sequra_Core::images/sequra-logo.png');

        return html_entity_decode(
            '<a class="sequra-link" href="' . $url . '" target="_blank" onclick="event.stopPropagation()">
                        <button class="sequra-preview">
                            <img class="sequra-logo" src=' . $imagePath . ' alt="sequra-logo">
                                ' . $this->translationProvider->translate("sequra.viewOnSequra") . '
                        </button>
                   </a>');
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
}
