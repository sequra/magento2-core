<?php

namespace Sequra\Core\Block;

use Exception;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Responses\GetWidgetsCheckoutResponse;
use SeQura\Core\Infrastructure\Logger\Logger;

/**
 * Class MiniWidget
 *
 * Implements required logic to show mini-widget according to configuration on product listing page
 */
class MiniWidget extends Template
{
    use WidgetTrait;

    /**
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param Context $context
     * @param Session $checkoutSession
     * @param Request $request
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface $localeResolver,
        Context $context,
        Session $checkoutSession,
        Request $request
    ) {
        parent::__construct($context);
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
    }

    /**
     * Prepares the available mini-widget to show based on the configuration and the current context
     *
     * @return mixed[]
     */
    public function getAvailableMiniWidgets(): array
    {
        try {
            $storeId = (string)$this->_storeManager->getStore()->getId();
            /** @var GetWidgetsCheckoutResponse $widget */
            $widget = CheckoutAPI::get()->promotionalWidgets($storeId)
                ->getAvailableMiniWidgetForProductListingPage(new PromotionalWidgetsCheckoutRequest(
                    $this->getShippingAddressCountry(),
                    $this->getCurrentCountry(),
                    $this->getCurrentCurrency(),
                    $this->getCustomerIpAddress()
                ));

            return $widget->isSuccessful() ? $widget->toArray() : [];
        } catch (Exception $e) {
            Logger::logError('Fetching available widgets on product listing page failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return [];
        }
    }
}
