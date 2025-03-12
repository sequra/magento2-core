<?php
namespace Sequra\Core\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;

class Teaser extends Template implements BlockInterface
{
    protected static $paymentCode;
    protected $_template = "widget/teaser.phtml";
    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $formatter;

    /**
     * @var IpAddressValidator
     */
    private $ipAddressValidator;
    /**
     * @var CurrencyValidator
     */
    private $currencyValidator;

    /**
     * @var ConnectionData
     */
    private $connectionSettings;

    /**
     * @var WidgetSettings
     */
    private $widgetSettings;

    /**
     * @var CountryConfiguration[]
     */
    private $countrySettings;

     /**
     * @return WidgetSettings|null
     */
    private function getWidgetSettings()
    {
        if(!$this->widgetSettings){
            try {
                $this->widgetSettings = StoreContext::doWithStore($this->scopeResolver->getScope()->getStoreId(), function () {
                    return ServiceRegister::getService(WidgetSettingsService::class)->getWidgetSettings();
                });
            } catch ( \Throwable $e ) {
                // TODO: Log error
            }
        }
        return $this->widgetSettings;
    }

     /**
     * @return CountryConfiguration[]|null
     */
    private function getCountrySettings()
    {
        if(!$this->countrySettings){
            try {
                $storeId = $this->scopeResolver->getScope()->getStoreId();
                $this->countrySettings = StoreContext::doWithStore($storeId, function () {
                    $settings = ServiceRegister::getService(CountryConfigurationService::class);
                    return $settings->getCountryConfiguration();
                });
            } catch ( \Throwable $e ) {
                // TODO: Log error
            }
        }
        return $this->countrySettings;
    }

     /**
     * @return ConnectionData|null
     */
    private function getConnectionSettings()
    {
        if(!$this->connectionSettings){
            try {
                $storeId = $this->scopeResolver->getScope()->getStoreId();
                $this->connectionSettings = StoreContext::doWithStore($storeId, function () {
                    $service = ServiceRegister::getService(ConnectionService::class);
                    return $service->getConnectionData();
                });
            } catch ( \Throwable $e ) {
                // TODO: Log error
            }
        }
        return $this->connectionSettings;
    }

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\View\Element\Template\Context $context,
        CurrencyValidator $currencyValidator,
        IpAddressValidator $ipAddressValidator,
        array $data = []
    ) {
        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        parent::__construct($context, $data);
        $this->formatter = $this->getFormatter();
        $this->currencyValidator = $currencyValidator;
        $this->ipAddressValidator = $ipAddressValidator;
    }
    
    /**
     * Validate before producing html
     *
     * @return string
     */
    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    protected function _toHtml()
    {
        $currency = $this->scopeResolver->getScope()->getCurrentCurrency();
        $storeId = $this->scopeResolver->getScope()->getStoreId();
        $subject = ['currency' => $currency->getCode(), 'storeId' => $storeId];
        
        if(!$this->currencyValidator->validate($subject)->isValid()){
            // return 'Currency not valid';
            return '';
        }
        
        if(!$this->ipAddressValidator->validate($subject)->isValid()){
            // return 'IP not valid';
            return '';
        }
        
        return parent::_toHtml();
    }
    // phpcs:enable

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

    // public function getMaxOrderTotal()
    // {
    //     return $this->config->getMaxOrderTotal();
    // }

    public function getScriptUri()
    {
        $settings = $this->getConnectionSettings();
        return !$settings || !$settings->getEnvironment() ? '' : "https://{$settings->getEnvironment()}.sequracdn.com/assets/sequra-checkout.min.js";
    }

    // public function getMinOrderTotal()
    // {
    //     return $this->config->getMinOrderTotal();
    // }

    public function getProduct()
    {
        return ["pp3"];
        // return $this->config->getProduct();
    }

    public function getAssetsKey()
    {
        $settings = $this->getWidgetSettings();
        return !$settings ? '' : $settings->getAssetsKey();
    }

    public function getMerchantRef()
    {
        $parts = explode('_', $this->localeResolver->getLocale());
        $country = strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
        $settingsArr = $this->getCountrySettings();
        if(is_array($settingsArr)){
            foreach($settingsArr as $settings){
                if($settings->getCountryCode() === $country){
                    return $settings->getMerchantId();
                }
            }
        }
        return '';
    }

    public function getLocale(){
        return str_replace('_','-',$this->localeResolver->getLocale());
    }

    // /** @deprecated */
    // public function getSilent(){
    //     return 'false';
    // }
}
