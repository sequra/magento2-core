<?php

namespace Sequra\Core\Block;

use Exception;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\Locale\ResolverInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Requests\PromotionalWidgetsCheckoutRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PromotionalWidgets\Responses\PromotionalWidgetsCheckoutResponse;
use SeQura\Core\Infrastructure\Logger\Logger;

class WidgetInitializer extends Template
{
    /**
     * @var ResolverInterface
     */
    protected ResolverInterface $localeResolver;

    /**
     * @var Session
     */
    private Session $session;

    /**
     * Constructor
     *
     * @param Context $context
     * @param ResolverInterface $localeResolver
     * @param Session $session
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        ResolverInterface $localeResolver,
        Session $session,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->localeResolver = $localeResolver;
        $this->session = $session;
    }

    /**
     * Returns data for widget initialization
     *
     * @return mixed[]
     */
    public function getWidgetInitializeData(): array
    {
        try {
            $quote = $this->session->getQuote();
            $shippingCountry = $quote->getShippingAddress()->getCountryId() ?? '';
            $storeId = (string)$this->_storeManager->getStore()->getId();
            $currentCountry = $this->getCurrentCountry();

            /** @var PromotionalWidgetsCheckoutResponse $widgetInitializeData */
            $widgetInitializeData = CheckoutAPI::get()
                ->promotionalWidgets($storeId)
                ->getPromotionalWidgetInitializeData(
                    new PromotionalWidgetsCheckoutRequest($shippingCountry, $currentCountry)
                );

            return $widgetInitializeData->isSuccessful() ? $widgetInitializeData->toArray() : [];
        } catch (Exception $e) {
            Logger::logError('Widget data initialization failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return [];
        }
    }

    /**
     * Get current country code
     *
     * @return string
     */
    private function getCurrentCountry(): string
    {
        $parts = explode('_', $this->localeResolver->getLocale());

        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }
}
