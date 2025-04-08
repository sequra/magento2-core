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
     * @return array
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function getConfig()
    {
        $currentStore = $this->storeManager->getStore();
        $settings = $this->widgetConfigService->getData($currentStore->getId());
        $generalSettingsResponse = AdminAPI::get()->generalSettings($currentStore->getId())->getGeneralSettings();
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
