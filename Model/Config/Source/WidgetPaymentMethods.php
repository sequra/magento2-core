<?php

namespace Sequra\Core\Model\Config\Source;

use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Data\OptionSourceInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Requests\GetCachedPaymentMethodsRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\PaymentMethods\Responses\CachedPaymentMethodsResponse;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
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
    public function __construct(ScopeResolverInterface $scopeResolver)
    {
        $this->scopeResolver = $scopeResolver;
    }

    /**
     * Returns and array containing data for each available payment method:
     * - countryCode: ISO 3166-1 alpha-2 country code
     * - product: Payment method product code
     * - campaign: Payment method campaign code
     *
     * @return array<array<string, string>> Array of payment method values
     */
    public function getPaymentMethodValues(): array
    {
        $values = [];
        $storeId = $this->scopeResolver->getScope()->getStoreId();
        $countries = $this->getAvailableCountries($storeId);
        foreach ($countries as $country) {
            if (!$country->getMerchantId()) {
                Logger::logInfo('Merchant id not found for storeId: ' . $storeId  . ' when fetching products');

                continue;
            }

            /** @var CachedPaymentMethodsResponse $cachedPaymentMethods */
            $cachedPaymentMethods = CheckoutAPI::get()->cachedPaymentMethods($storeId)
                ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($country->getMerchantId()));
            foreach ($cachedPaymentMethods->toArray() as $paymentMethod) {
                if (!in_array($paymentMethod['product'], ['i1', 'pp5', 'pp3', 'pp6', 'pp9', 'sp1'], true)) {
                    continue;
                }

                $values[] = [
                    'countryCode' => $country->getCountryCode(),
                    'product' => $paymentMethod['product'],
                    'campaign' => $paymentMethod['campaign']
                ];
            }
        }
        return $values;
    }

    /**
     * Encode payment method value to be used as option value
     *
     * @param array<string, string> $value Payment method value
     *
     * @return string Encoded value
     */
    public function encodePaymentMethodValue(array $value): string
    {
        return base64_encode(json_encode($value));
    }

    /**
     * Get options array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->getPaymentMethodValues() as $value) {
            $options[] = [
                'value' => $this->encodePaymentMethodValue($value),
                'label' => "{$this->getCountryFlag($value['countryCode'])} {$value['product']}"
            ];
        }

        // reorder by label
        usort($options, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $options;
    }

    /**
     * Get available countries configurations
     *
     * @param string $storeId
     *
     * @return CountryConfiguration[]
     */
    private function getAvailableCountries(string $storeId): array
    {
        $countries = [];
        try {
            $countries = StoreContext::doWithStore($storeId, function () {
                return ServiceRegister::getService(CountryConfigurationService::class)->getCountryConfiguration();
            });
        } catch (\Throwable $e) {
            // TODO: Log error
        }
        return $countries;
    }

    /**
     * Convert country code to flag emoji
     *
     * @param string $countryCode ISO 3166-1 alpha-2 country code
     *
     * @return string Flag emoji
     */
    private function getCountryFlag(string $countryCode): string
    {
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
