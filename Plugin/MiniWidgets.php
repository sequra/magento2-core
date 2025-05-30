<?php

namespace Sequra\Core\Plugin;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Pricing\Render\Amount;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Responses\GetWidgetsCheckoutResponse;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Block\WidgetTrait;
use Magento\Framework\Locale\ResolverInterface;

/**
 * Class MiniWidget
 *
 * Implements required logic to show mini-widget according to configuration on product listing page
 */
class MiniWidgets
{

    use WidgetTrait;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Escaper
     */
    private $htmlEscaper;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param Session $checkoutSession
     * @param Request $request
     * @param Escaper $htmlEscaper
     */
    public function __construct(
        StoreManagerInterface  $storeManager,
        ScopeResolverInterface $scopeResolver,
        ResolverInterface      $localeResolver,
        Session                $checkoutSession,
        Request                $request,
        Escaper                $htmlEscaper
    ) {
        $this->storeManager = $storeManager;
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->htmlEscaper = $htmlEscaper;
    }

    /**
     * Runs after the toHtml method and calls CORE logic for getting mini-widget data
     *
     * @param Amount $amount
     * @param string $result
     *
     * @return string
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function afterToHtml(Amount $amount, string $result): string
    {
        if ($amount->getData('zone') !== 'item_list' || $amount->getData('price_type') !== 'finalPrice') {
            return $result;
        }

        // @phpstan-ignore-next-line
        /** @var Product $product */
        $product = $amount->getPrice() ? $amount->getPrice()->getProduct() : null;
        if (!$product) {
            return $result;
        }

        try {
            $storeId = (string)$this->storeManager->getStore()->getId();
            /** @var GetWidgetsCheckoutResponse $miniWidget */
            $miniWidget = CheckoutAPI::get()->promotionalWidgets($storeId)
                ->getAvailableMiniWidgetForProductListingPage(new PromotionalWidgetsCheckoutRequest(
                    $this->getShippingAddressCountry(),
                    $this->getCurrentCountry(),
                    $this->getCurrentCurrency(),
                    $this->getCustomerIpAddress(),
                    $product->getId()
                ));

            $result .= $this->getHtml($miniWidget->isSuccessful() ? $miniWidget->toArray() : []);
        } catch (Exception $e) {
            Logger::logError('Fetching available widgets on product listing page failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());
        }

        return $result;
    }

    /**
     * Gets the HTML with mini-widget dataset
     *
     * @param array $miniWidgetData
     *
     * @return string
     */
    private function getHtml(array $miniWidgetData): string
    {
        if (empty($miniWidgetData)) {
            return '';
        }

        $datasetString = '';
        foreach ($miniWidgetData as $miniWidget) {
            $dataset = [
                'product' => $miniWidget['product'],
                'price-sel' => '.product-item-info ' . $miniWidget['priceSel'],
                'dest' => '.product-item-info ' . $miniWidget['dest'],
                'min-amount' => $miniWidget['minAmount'],
                'max-amount' => $miniWidget['maxAmount'],
                'message' => $miniWidget['miniWidgetMessage'],
                'message-below-limit' => $miniWidget['miniWidgetBelowLimitMessage'],
            ];

            $dataset = array_map(
                function (string $key, string $value) {
                    /** @var string $escapedValue */
                    $escapedValue = $this->htmlEscaper->escapeHtml((string)$value);
                    return sprintf('data-%s="%s"', $key, $escapedValue);
                },
                array_keys($dataset),
                $dataset
            );
            $datasetString .= implode(' ', $dataset);
        }

        return "<div class=\"sequra-educational-popup sequra-promotion-miniwidget\" $datasetString></div>";
    }
}
