<?php

namespace Sequra\Core\Setup\Patch\Data;

use Exception;
use JsonException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use SeQura\Core\BusinessLogic\DataAccess\PromotionalWidgets\Entities\WidgetSettings as WidgetSettingsEntity;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Models\CountryConfiguration;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\CustomWidgetsSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSelectorSettings;
use SeQura\Core\Infrastructure\Logger\Logger;
use SeQura\Core\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use SeQura\Core\Infrastructure\ORM\Interfaces\RepositoryInterface;
use SeQura\Core\Infrastructure\ORM\RepositoryRegistry;
use SeQura\Core\Infrastructure\ServiceRegister;

/**
 * Class Version270
 *
 * Migration script for transitioning widgets based on the Teaser block
 * to new widgets that extend a template and are configurable through the integration.
 *
 */
class Version270 implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    )
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();

        try {

            // Fetch teaser data from Magento 2 database
            $teaser = $this->fetchFirstTeaserAndRemoveAll($connection);
            $storeIds = explode(',', $teaser['store_ids']);
            $widget_parameters = json_decode($teaser['widget_parameters'], true, 512, JSON_THROW_ON_ERROR);
            $priceSelector = $widget_parameters['price_sel'];
            $destinationSelector = $widget_parameters['dest_sel'];
            $theme = $widget_parameters['theme'];
            $teaserPaymentMethods = $this->getPaymentMethodsData(
                array_key_exists('payment_methods', $widget_parameters) ?
                    $widget_parameters['payment_methods'] : []
            );

            $widgetSettingsEntities = $this->getAllWidgetSettingsEntities();
            foreach ($widgetSettingsEntities as $widgetSettingsEntity) {
                $storeId = $widgetSettingsEntity->getStoreId();
                StoreContext::doWithStore($storeId, function () {
                    return $this->getPaymentMethodsService()->getAvailablePaymentMethodsForAllMerchants(true);
                });

                if (!in_array($storeId, $storeIds, true)) {
                    continue;
                }

                $this->migrateWidgetConfigurationForStore(
                    $storeId,
                    $priceSelector,
                    $destinationSelector,
                    $theme,
                    $teaserPaymentMethods,
                    $widgetSettingsEntity
                );
            }

            $this->moduleDataSetup->endSetup();
        } catch (Exception $e) {
            $connection->rollBack();

            Logger::logError('Migration ' . self::class . ' failed with error: ' . $e->getMessage() .
                ' Trace :' . $e->getTraceAsString());
        }
    }

    /**
     * Returns first Sequra Teaser from database and removes all teaser in database
     *
     * @param AdapterInterface $connection
     *
     * @return array
     */
    private function fetchFirstTeaserAndRemoveAll(AdapterInterface $connection): array
    {
        $widget_instance = $this->moduleDataSetup->getTable('widget_instance');
        $query = $connection->select()->from($widget_instance)->where(
            'instance_type = ?', 'Sequra\Core\Block\Widget\Teaser',
        );
        $teasers = $connection->fetchAll($query);

        if (empty($teasers)) {
            return [];
        }

        //Remove all Sequra teasers from database
        $ids = [];
        foreach ($teasers as $teaser) {
            $ids[] = $teaser['instance_id'];
        }

        $connection->delete($widget_instance, ['instance_id IN (?)' => $ids]);

        return $teasers[0];
    }


    /**
     * Decodes payment methods data from teaser payment methods data
     *
     * @param array $paymentMethods
     *
     * @return array
     * @throws JsonException
     */
    private function getPaymentMethodsData(array $paymentMethods): array
    {
        return array_map(
            static function ($value) {
                // TODO: The use of function base64_decode() is discouraged
                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                return json_decode(base64_decode($value), true, 512, JSON_THROW_ON_ERROR);
            },
            $paymentMethods
        );
    }

    /**
     * Filters payment methods that are available in store and update widget settings entity with teaser data
     *
     * @param string $storeId
     * @param string $priceSelector
     * @param string $destinationsSelector
     * @param string $theme
     * @param array $teaserPaymentMethods
     * @param WidgetSettingsEntity $widgetSettingsEntity
     *
     * @return void
     * @throws RepositoryNotRegisteredException
     * @throws Exception
     */
    private function migrateWidgetConfigurationForStore(
        string               $storeId,
        string               $priceSelector,
        string               $destinationsSelector,
        string               $theme,
        array                $teaserPaymentMethods,
        WidgetSettingsEntity $widgetSettingsEntity
    ): void
    {
        $availableCountries = $this->getAvailableCountries($storeId);
        $paymentMethods = [];
        foreach ($teaserPaymentMethods as $teaserPaymentMethod) {
            if (is_array($teaserPaymentMethod)
                && isset($teaserPaymentMethod['countryCode'], $teaserPaymentMethod['product'])
                && in_array($teaserPaymentMethod['countryCode'], $availableCountries, true)
            ) {
                $paymentMethods[] = $teaserPaymentMethod['product'];
            }
        }

        $this->updateWidgetSettingEntity(
            $widgetSettingsEntity,
            $priceSelector,
            $destinationsSelector,
            $theme,
            $paymentMethods
        );
    }

    /**
     * Updates widget settings entity with teaser data
     *
     * @param WidgetSettingsEntity $widgetSettingsEntity
     * @param string $priceSelector
     * @param string $destinationSelector
     * @param string $theme
     * @param array $paymentMethods
     *
     * @return void
     * @throws RepositoryNotRegisteredException
     */
    private function updateWidgetSettingEntity(
        WidgetSettingsEntity $widgetSettingsEntity,
        string               $priceSelector,
        string               $destinationSelector,
        string               $theme,
        array                $paymentMethods
    ): void
    {
        $widgetSettings = $widgetSettingsEntity->getWidgetSettings();
        $widgetSettingsForProduct = new WidgetSelectorSettings(
            $priceSelector,
            $destinationSelector
        );
        $customWidgetSettings = [];
        foreach ($paymentMethods as $paymentMethod) {
            $customWidgetSettings[] = new CustomWidgetsSettings(
                '',
                $paymentMethod,
                true,
                $theme
            );
        }

        $widgetSettingsForProduct->setCustomWidgetsSettings($customWidgetSettings);
        $widgetSettings->setWidgetSettingsForProduct($widgetSettingsForProduct);
        $widgetSettingsEntity->setWidgetSettings($widgetSettings);

        $this->getWidgetSettingRepository()->update($widgetSettingsEntity);
    }


    /**
     * Returns country codes for available countries in store
     *
     * @param string $storeId
     * @return array
     * @throws Exception
     */
    private function getAvailableCountries(string $storeId): array
    {
        /** @var CountryConfiguration[]|null $countries */
        $countries = StoreContext::doWithStore($storeId, function () {
            return $this->getCountryConfigurationService()->getCountryConfiguration();
        });

        return array_map(static function ($country) {
            return $country->getCountryCode();
        }, $countries);
    }

    /**
     * Returns all widget settings entities
     *
     * @return WidgetSettingsEntity[]
     * @throws RepositoryNotRegisteredException
     */
    private function getAllWidgetSettingsEntities(): array
    {
        return $this->getWidgetSettingRepository()->select();
    }

    /**
     * Returns payment methods service
     *
     * @return PaymentMethodsService
     */
    private function getPaymentMethodsService(): PaymentMethodsService
    {
        return ServiceRegister::getService(PaymentMethodsService::class);
    }

    /**
     * Returns country configuration service
     *
     * @return CountryConfigurationService
     */
    private function getCountryConfigurationService(): CountryConfigurationService
    {
        return ServiceRegister::getService(CountryConfigurationService::class);
    }

    /**
     * Returns widget settings entity repository
     *
     * @return RepositoryInterface
     * @throws RepositoryNotRegisteredException
     */
    private function getWidgetSettingRepository(): RepositoryInterface
    {
        return RepositoryRegistry::getRepository(WidgetSettingsEntity::class);
    }
}
