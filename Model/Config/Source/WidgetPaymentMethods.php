<?php

namespace Sequra\Core\Model\Config\Source;

use Magento\Store\Model\StoreManagerInterface;
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
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Constructor
     *
     * @param StoreManagerInterface $storeManager Store manager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * Returns and array containing data for each available payment method:
     * - countryCode: ISO 3166-1 alpha-2 country code
     * - product: Payment method product code
     * - campaign: Payment method campaign code
     *
     * @return array<int<0, max>, array<string, string|null>> Array of payment method values
     */
    public function getPaymentMethodValues()
    {
        $values = [];
        $storeId = (string) $this->storeManager->getStore()->getId();
        $countries = $this->getAvailableCountries($storeId);
        foreach ($countries as $country) {
            if (!$country->getMerchantId()) {
                Logger::logInfo('Merchant id not found for storeId: ' . $storeId . ' when fetching products');

                continue;
            }

            /** @var CachedPaymentMethodsResponse $cachedPaymentMethods */
            $cachedPaymentMethods = CheckoutAPI::get()->cachedPaymentMethods($storeId)
                ->getCachedPaymentMethods(new GetCachedPaymentMethodsRequest($country->getMerchantId()));
            foreach ($cachedPaymentMethods->toArray() as $paymentMethod) {
                if (!is_array($paymentMethod) ||
                    (
                        isset($paymentMethod['product']) &&
                        !in_array($paymentMethod['product'], ['i1', 'pp5', 'pp3', 'pp6', 'pp9', 'sp1'], true)
                    )
                ) {
                    continue;
                }

                $values[] = [
                    'countryCode' => $country->getCountryCode(),
                    'product' => $paymentMethod['product'],
                    'campaign' => isset($paymentMethod['campaign']) &&
                        is_string($paymentMethod['campaign']) ? $paymentMethod['campaign'] : null,
                ];
            }
        }
        return $values;
    }

    /**
     * Encode payment method value to be used as option value
     *
     * @param array $value Payment method value
     * @phpstan-param array<string, string|null> $value
     *
     * @return string Encoded value
     */
    public function encodePaymentMethodValue($value)
    {
        $json = json_encode($value);
        return is_string($json) ? base64_encode($json) : '';
    }

    /**
     * Get options array
     *
     * @phpstan-return array<int, array{value: string, label: string}>
     * @return array<int<0, max>, array<string, string>> Options array
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
    private function getAvailableCountries($storeId)
    {
        $countries = [];
        try {
            /**
             * @var CountryConfiguration[] $countries
             */
            $countries = StoreContext::doWithStore($storeId, function () {
                return ServiceRegister::getService(CountryConfigurationService::class)->getCountryConfiguration();
            });
            // TODO: Log error
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
        } catch (\Throwable $e) {
        }
        return $countries;
    }

    /**
     * Convert country code to flag emoji
     *
     * @param string|null $countryCode ISO 3166-1 alpha-2 country code
     *
     * @return string Flag emoji
     */
    private function getCountryFlag($countryCode)
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
