<?php

namespace Sequra\Core\Block;

use Exception;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Responses\GetWidgetsCheckoutResponse;
use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;
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
     * @param CurrencyValidator $currencyValidator
     * @param IpAddressValidator $ipAddressValidator
     * @param Context $context
     * @param Session $checkoutSession
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface      $localeResolver,
        CurrencyValidator      $currencyValidator,
        IpAddressValidator     $ipAddressValidator,
        Context                $context,
        Session                $checkoutSession
    ) {
        parent::__construct($context);
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->currencyValidator = $currencyValidator;
        $this->ipAddressValidator = $ipAddressValidator;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Validate before producing html
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->validate()) {
            return '';
        }

        return parent::_toHtml();
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
                    $this->getCurrentCountry()
                ));

            return $widget->isSuccessful() ? $widget->toArray() : [];
        } catch (Exception $e) {
            Logger::logError('Fetching available widgets on product listing page failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return [];
        }
    }
}
