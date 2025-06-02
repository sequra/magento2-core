<?php

namespace Sequra\Core\Block;

use Exception;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\App\Request\Http;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Responses\GetWidgetsCheckoutResponse;
use SeQura\Core\Infrastructure\Logger\Logger;

/**
 * Class Product
 *
 * Implements required logic to show widget in the product page
 */
class Product extends Template
{
    use WidgetTrait;

    /**
     * @var Http
     */
    protected $http;

    /**
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param Context $context
     * @param Session $checkoutSession
     * @param Request $request
     * @param Http $http
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface $localeResolver,
        Context $context,
        Session $checkoutSession,
        Request $request,
        Http $http
    ) {
        parent::__construct($context);
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->http = $http;
    }

    /**
     * Prepares the available widget to show in the cart frontend based on the configuration and the current context
     *
     * @return mixed[]
     */
    public function getAvailableWidgets(): array
    {
        try {
            $productId = $this->http->getParam('id');
            if (!is_string($productId) && !is_int($productId)) {
                return [];
            }

            $storeId = (string)$this->_storeManager->getStore()->getId();

            /** @var GetWidgetsCheckoutResponse $widgets */
            $widgets = CheckoutAPI::get()->promotionalWidgets($storeId)
                ->getAvailableWidgetsForProductPage(new PromotionalWidgetsCheckoutRequest(
                    $this->getShippingAddressCountry(),
                    $this->getCurrentCountry(),
                    $this->getCurrentCurrency(),
                    $this->getCustomerIpAddress(),
                    (string)$productId
                ));

            return $widgets->isSuccessful() ? $widgets->toArray() : [];
        } catch (Exception $e) {
            Logger::logError('Fetching available widgets on product page failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return [];
        }
    }
}
