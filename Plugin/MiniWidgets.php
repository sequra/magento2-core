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
                    (string)$product->getId()
                ));

            if ($miniWidget->isSuccessful()) {
                /** @var array<array{
                 *     product?: string,
                 *     priceSel?: string,
                 *     dest?: string,
                 *     minAmount?: int,
                 *     maxAmount?: int,
                 *     miniWidgetMessage?: string,
                 *     miniWidgetBelowLimitMessage?: string
                 * }> $data
                 */
                $data = $miniWidget->toArray();
                $cents = (int) round($amount->getPrice()->getAmount()->getValue() * 100);
                $result .= $this->getHtml($data, $cents);
            }
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
     * @param int $amount
     * @phpstan-param array<array{
     * product?: string,
     * priceSel?: string,
     * dest?: string,
     * minAmount?: int,
     * maxAmount?: int,
     * miniWidgetMessage?: string,
     * miniWidgetBelowLimitMessage?: string
     * }> $miniWidgetData
     *
     * @return string
     */
    protected function getHtml(array $miniWidgetData, int $amount): string
    {
        if (empty($miniWidgetData)) {
            return '';
        }

        $datasetString = '';
        foreach ($miniWidgetData as $miniWidget) {
            $dataset = [
                'product' => $miniWidget['product'] ?? '',
                'min-amount' => $miniWidget['minAmount'] ?? '',
                'max-amount' => $miniWidget['maxAmount'] ?? '',
                'message' => $miniWidget['miniWidgetMessage'] ?? '',
                'message-below-limit' => $miniWidget['miniWidgetBelowLimitMessage'] ?? '',
                'amount' => $amount
            ];

            $dataset = array_map(
                function ($key, $value) {
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
