<?php

namespace Sequra\Core\Block\Widget;

use Sequra\Core\Gateway\Validator\CurrencyValidator;
use Sequra\Core\Gateway\Validator\IpAddressValidator;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Connection\Models\ConnectionData;
use SeQura\Core\BusinessLogic\Domain\Connection\Services\ConnectionService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;

trait WidgetTrait
{

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $localeResolver;

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
     * Get widget settings
     *
     * @return WidgetSettings|null
     */
    private function getWidgetSettings()
    {
        if (!$this->widgetSettings) {
            try {
                $storeId = (string) $this->_storeManager->getStore()->getId();
                /**
                 * @var ?WidgetSettings $widgetSettings
                 */
                $widgetSettings = StoreContext::doWithStore($storeId, function () {
                    /**
                     * @var WidgetSettingsService $service
                     */
                    $service = ServiceRegister::getService(WidgetSettingsService::class);
                    return $service->getWidgetSettings();
                });

                if (!$widgetSettings) {
                    return null;
                }

                $this->widgetSettings = $widgetSettings;

                // TODO: Log error
                // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Throwable $e) {
            }
        }
        return $this->widgetSettings;
    }

    /**
     * Get country settings
     *
     * @return CountryConfiguration[]|null
     */
    private function getCountrySettings()
    {
        if (!$this->countrySettings) {
            try {
                $storeId = (string) $this->_storeManager->getStore()->getId();
                /**
                 * @var ?CountryConfiguration[] $countrySettings
                 */
                $countrySettings = StoreContext::doWithStore($storeId, function () {
                    /**
                     * @var CountryConfigurationService $settings
                     */
                    $settings = ServiceRegister::getService(CountryConfigurationService::class);
                    return $settings->getCountryConfiguration();
                });
                if (!$countrySettings) {
                    return null;
                }
                $this->countrySettings = $countrySettings;
                // TODO: Log error
                // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Throwable $e) {
            }
        }
        return $this->countrySettings;
    }

    /**
     * Get connection settings
     *
     * @return ConnectionData|null
     */
    private function getConnectionSettings()
    {
        if (!$this->connectionSettings) {
            try {
                $storeId = (string) $this->_storeManager->getStore()->getId();
                /**
                 * @var ?ConnectionData $connectionSettings
                 */
                $connectionSettings = StoreContext::doWithStore($storeId, function () {
                    /**
                     * @var ConnectionService $service
                     */
                    $service = ServiceRegister::getService(ConnectionService::class);
                    return $service->getConnectionData();
                });
                if (!$connectionSettings) {
                    return null;
                }
                $this->connectionSettings = $connectionSettings;
                // TODO: Log error
                // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
            } catch (\Throwable $e) {
            }
        }
        return $this->connectionSettings;
    }

    /**
     * Get current country code from locale.
     *
     * @return string
     */
    private function getCurrentCountry()
    {
        $parts = explode('_', $this->localeResolver->getLocale());
        return strtoupper(count($parts) > 1 ? $parts[1] : $parts[0]);
    }

    /**
     * Get merchant ID for current country.
     *
     * @return string
     */
    private function getMerchantId()
    {
        $country = $this->getCurrentCountry();
        $settingsArr = $this->getCountrySettings();
        if (is_array($settingsArr)) {
            foreach ($settingsArr as $settings) {
                if ($settings->getCountryCode() === $country) {
                    return $settings->getMerchantId();
                }
            }
        }
        return '';
    }

    /**
     * Get formatted locale for widget.
     *
     * @return string
     */
    public function getLocale()
    {
        return str_replace('_', '-', $this->localeResolver->getLocale());
    }
}
