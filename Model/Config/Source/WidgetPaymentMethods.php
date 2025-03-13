<?php
namespace Sequra\Core\Model\Config\Source;

use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Data\OptionSourceInterface;


use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Models\SeQuraPaymentMethod;

use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;

class WidgetPaymentMethods implements OptionSourceInterface
{
    /**
     * @var ScopeResolverInterface
     */
    private $scopeResolver;

    /**
     * Constructor
     */
    public function __construct(ScopeResolverInterface $scopeResolver) {
        $this->scopeResolver = $scopeResolver;
    }

    /**
     * Get options array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $storeId = $this->scopeResolver->getScope()->getStoreId();
        
        $countries = $this->getAvailableCountries($storeId);
        foreach ($countries as $country) {
            
            if(!$country->getMerchantId()){
                // TODO: Log Merchant ID not found
                continue;
            }

            $payment_methods = $this->getPaymentMethods($storeId, $country->getMerchantId());
            foreach ($payment_methods as $payment_method) {
                // Check if supports widgets
                if(!in_array( $payment_method->getProduct(), array( 'i1', 'pp5', 'pp3', 'pp6', 'pp9', 'sp1' ), true )){
                    continue;
                }

                $value = [
                    'countyCode' => $country->getCountryCode(),
                    'product' => $payment_method->getProduct(),
                    'campaign' => $payment_method->getCampaign() 
                ];
                $value = base64_encode(json_encode($value));

                $options[] = [
                    'value' => $value,
                    'label' => "{$this->getCountryFlag($country->getCountryCode())} {$payment_method->getTitle()}"
                ];
            }

        }

        // reorder by label
        usort($options, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $options;
    }

    /**
     * Get available countries configurations
     * @param string $storeId
     * @return CountryConfiguration[]
     */
    private function getAvailableCountries($storeId){
        $countries = [];
        try {
            $countries = StoreContext::doWithStore($storeId, function () {
                return ServiceRegister::getService(CountryConfigurationService::class)->getCountryConfiguration();
            });
        } catch ( \Throwable $e ) {
            // TODO: Log error
        }
        return $countries;
    }

    /**
     * Get payment methods for a given merchant using the current store context
     * @param string $storeId
     * @param string $merchantId
     * @return SeQuraPaymentMethod[]
     */
    private function getPaymentMethods($storeId, $merchantId){
        $payment_methods = [];
        try {
            $payment_methods = StoreContext::doWithStore($storeId, function () use ( $merchantId ) {
                return ServiceRegister::getService(PaymentMethodsService::class)->getMerchantsPaymentMethods($merchantId);
            });
        } catch ( \Throwable $e ) {
            // TODO: Log error
        }
        return $payment_methods;
    }

    /**
     * Convert country code to flag emoji
     * 
     * @param string $countryCode ISO 3166-1 alpha-2 country code
     * @return string Flag emoji
     */
    private function getCountryFlag($countryCode) {
        if (empty($countryCode) || strlen($countryCode) !== 2) {
            return '';
        }
        
        // Convert each letter to their regional indicator symbol
        // Regional indicator symbols are 127397 (0x1F1E6) higher than ASCII uppercase letters
        $letterOffset = 127397; // 0x1F1E6 - 'A'
        
        $firstLetter = mb_ord(mb_strtoupper($countryCode[0])) + $letterOffset;
        $secondLetter = mb_ord(mb_strtoupper($countryCode[1])) + $letterOffset;
        
        return mb_chr($firstLetter) . mb_chr($secondLetter);
    }
}