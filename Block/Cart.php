<?php

namespace Sequra\Core\Block;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Tests\NamingConvention\true\mixed;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;
use SeQura\Core\Infrastructure\Logger\Logger;

/**
 * Class Cart
 *
 * Implements required logic to show widget in the cart page
 */
class Cart extends Template
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
        ResolverInterface $localeResolver,
        CurrencyValidator $currencyValidator,
        IpAddressValidator $ipAddressValidator,
        Context $context,
        Session $checkoutSession
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
     *
     * @throws LocalizedException
     */
    protected function _toHtml(): string
    {
        if (!$this->validate()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Prepares the available widget to show in the cart frontend based on the configuration and the current context
     *
     * @return mixed[]
     */
    public function getAvailableWidgets(): array
    {
        try {
            $storeId = (string)$this->_storeManager->getStore()->getId();
            $widget = CheckoutAPI::get()->promotionalWidgets($storeId)
                ->getAvailableWidgetForCartPage(new PromotionalWidgetsCheckoutRequest(
                    $this->getShippingAddressCountry(),
                    $this->getCurrentCountry()
                ));

            return $widget->toArray();
        } catch (Exception $e) {
            Logger::logError('Fetching available widgets on cart page failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return [];
        }
    }
}
