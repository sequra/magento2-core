<?php

namespace Sequra\Core\Model\Ui;

use Exception;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Sequra\Core\Services\BusinessLogic\WidgetConfigService;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'sequra_payment';

    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var WidgetConfigService
     */
    protected $widgetConfigService;

    public function __construct(
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface   $localeResolver,
        StoreManagerInterface                         $storeManager,
        WidgetConfigService $widgetConfigService
    )
    {
        $this->localeResolver = $localeResolver;
        $this->scopeResolver = $scopeResolver;
        $this->storeManager = $storeManager;
        $this->widgetConfigService = $widgetConfigService;
        $this->formatter = $this->getFormatter();
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function getConfig()
    {
        $currentStore = $this->storeManager->getStore();
        $settings = $this->widgetConfigService->getData($currentStore->getId());

        return [
            'payment' => [
                self::CODE => [
                    'showwidgets' => !empty($settings['assetKey']),
                    'widget_settings' => $settings,
                    'decimalSeparator' => $this->getDecimalSeparator(),
                    'thousandSeparator' => $this->getThousandsSeparator(),
                    'locale' => str_replace('_', '-', $this->localeResolver->getLocale()),
                    'showlogo' => true,
                ]
            ]
        ];
    }

    private function getFormatter()
    {
        $localeCode = $this->localeResolver->getLocale();
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
        return new \NumberFormatter(
            $localeCode . '@currency=' . $currency->getCode(),
            \NumberFormatter::CURRENCY
        );
    }

    public function getDecimalSeparator()
    {
        return $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    public function getThousandsSeparator()
    {
        return $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }
}
