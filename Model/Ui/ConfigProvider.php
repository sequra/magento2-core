<?php

namespace Sequra\Core\Model\Ui;

use Exception;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use Sequra\Core\Services\BusinessLogic\WidgetConfigService;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'sequra_payment';

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
    /**
     * @var UrlInterface
     */
    private $urlBuilder;
    /**
     * @var \NumberFormatter
     */
    private $formatter;

    /**
     * ConfigProvider constructor.
     *
     * @param \Magento\Framework\App\ScopeResolverInterface $scopeResolver
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param StoreManagerInterface $storeManager
     * @param WidgetConfigService $widgetConfigService
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface   $localeResolver,
        StoreManagerInterface                         $storeManager,
        WidgetConfigService $widgetConfigService,
        UrlInterface $urlBuilder
    ) {
        $this->localeResolver = $localeResolver;
        $this->scopeResolver = $scopeResolver;
        $this->storeManager = $storeManager;
        $this->widgetConfigService = $widgetConfigService;
        $this->formatter = $this->getFormatter();
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @throws NoSuchEntityException
     * @throws Exception
     *
     * @phpstan-return array<string, array<string, array<string, mixed>>>
     * @return array
     */
    public function getConfig()
    {
        $currentStore = $this->storeManager->getStore();
        $storeId = (string) $currentStore->getId();
        $settings = $this->widgetConfigService->getData($storeId);
        // @phpstan-ignore-next-line
        $generalSettingsResponse = AdminAPI::get()->generalSettings($storeId)->getGeneralSettings();
        $showFormAsHostedPage = false;
        if ($generalSettingsResponse->isSuccessful()) {
            $showFormAsHostedPage = $generalSettingsResponse->toArray()['showSeQuraCheckoutAsHostedPage'] ?? false;
        }

        return [
            'payment' => [
                self::CODE => [
                    'showwidgets' => !empty($settings['assetKey']),
                    'widget_settings' => $settings,
                    'decimalSeparator' => $this->getDecimalSeparator(),
                    'thousandSeparator' => $this->getThousandsSeparator(),
                    'locale' => str_replace('_', '-', $this->localeResolver->getLocale()),
                    'showlogo' => true,
                    'showSeQuraCheckoutAsHostedPage' => $showFormAsHostedPage,
                    'sequraCheckoutHostedPage' => $this->urlBuilder->getUrl('sequra/hpp'),
                ]
            ]
        ];
    }

    /**
     * Get the formatter for the current locale and currency.
     *
     * @return \NumberFormatter
     */
    private function getFormatter()
    {
        $localeCode = $this->localeResolver->getLocale();
        // TODO: Call to an undefined method Magento\Framework\App\ScopeInterface::getCurrentCurrency()
        // @phpstan-ignore-next-line
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
        return new \NumberFormatter(
            $localeCode . '@currency=' . $currency->getCode(),
            \NumberFormatter::CURRENCY
        );
    }

    /**
     * Get the decimal separator for the current locale and currency.
     *
     * @return string
     */
    public function getDecimalSeparator()
    {
        return (string) $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }

    /**
     * Get the thousands separator for the current locale and currency.
     *
     * @return string
     */
    public function getThousandsSeparator()
    {
        return (string) $this->formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    }
}
