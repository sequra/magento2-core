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
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Models\SeQuraPaymentMethod;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\CustomWidgetsSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSelectorSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
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

    private const WIDGET_STYLE_MAP = [
        'L'        => ['alignment' => 'left'],
        'R'        => ['alignment' => 'right'],
        'legacy'   => ['type' => 'legacy'],
        'legacyL'  => ['type' => 'legacy', 'alignment' => 'left'],
        'legacyR'  => ['type' => 'legacy', 'alignment' => 'right'],
        'minimal'  => ['type' => 'text', 'branding' => 'none', 'size' => 'S', 'starting-text' => 'as-low-as'],
        'minimalL' => [
            'type' => 'text',
            'branding' => 'none',
            'size' => 'S',
            'starting-text' => 'as-low-as',
            'alignment' => 'left'
        ],
        'minimalR' => [
            'type' => 'text',
            'branding' => 'none',
            'size' => 'S',
            'starting-text' => 'as-low-as',
            'alignment' => 'right'
        ]
    ];
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
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
     *
     * @throws Exception
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();

        try {
            $connection->beginTransaction();

            $widgetSettingsEntities = $this->getAllWidgetSettingsEntities();
            $arrayOfWidgetSettingsEntities = [];
            $arrayOfAvailablePaymentMethodsPerStore = [];

            // Get available Sequra payment methods with new category property and cache them for every store
            foreach ($widgetSettingsEntities as $widgetSettingsEntity) {
                $storeId = $widgetSettingsEntity->getStoreId();
                /** @var SeQuraPaymentMethod[] $paymentMethods */
                $paymentMethods = StoreContext::doWithStore($storeId, function () {
                    return $this->getPaymentMethodsService()->getAvailablePaymentMethodsForAllMerchants(true);
                });

                $arrayOfWidgetSettingsEntities[$storeId] = $widgetSettingsEntity;
                $arrayOfAvailablePaymentMethodsPerStore[$storeId] = $paymentMethods;
            }

            // Remove all Sequra layout blocks from Magento 2 database
            $this->removeAllTeaserLayoutBlocks($connection);

            // Fetch all Sequra teasers from Magento 2 database and remove them
            $teasers = $this->fetchAllTeasersAndRemoveThem($connection);
            if (empty($teasers)) {
                $connection->commit();
                $this->moduleDataSetup->endSetup();

                return;
            }

            $this->migrateTeasersData($teasers, $arrayOfWidgetSettingsEntities);

            $this->disableUnconfiguredPaymentMethods($arrayOfAvailablePaymentMethodsPerStore);

            $connection->commit();
            Logger::logInfo('Migration ' . self::class . ' has been successfully finished.');
        } catch (Exception $e) {
            $connection->rollBack();

            Logger::logError('Migration ' . self::class . ' failed with error: ' . $e->getMessage() .
                ' Trace :' . $e->getTraceAsString());
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * Returns all Sequra Teasers from database and removes them
     *
     * @param AdapterInterface $connection
     *
     * @return array<string>
     */
    private function fetchAllTeasersAndRemoveThem(AdapterInterface $connection): array
    {
        $widgetInstance = $this->moduleDataSetup->getTable('widget_instance');
        $query = $connection->select()->from($widgetInstance)->where(
            'instance_type LIKE ?',
            '%Sequra_Core_Block_Widget_Teaser%',
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

        $connection->delete($widgetInstance, ['instance_id IN (?)' => $ids]);

        return $teasers;
    }

    /**
     * Removes all Sequra Teaser Layout Blocks from database
     *
     * @param AdapterInterface $connection
     *
     * @return void
     */
    private function removeAllTeaserLayoutBlocks(AdapterInterface $connection): void
    {
        $layoutUpdate = $this->moduleDataSetup->getTable('layout_update');
        $query = $connection->select()->from($layoutUpdate)->where(
            'xml LIKE ?',
            '%Sequra_Core_Block_Widget_Teaser%',
        );
        $layoutBlocks = $connection->fetchAll($query);

        if (empty($layoutBlocks)) {
            return;
        }

        //Remove all Sequra layout blocks from database
        $ids = [];
        foreach ($layoutBlocks as $layoutBlock) {
            $ids[] = $layoutBlock['layout_update_id'];
        }

        $connection->delete($layoutUpdate, ['layout_update_id IN (?)' => $ids]);
    }

    /**
     * Migrates data from all Sequra teasers to new widget configurations.
     *
     * @param array<string> $teasers
     * @param array<WidgetSettingsEntity> $arrayOfWidgetSettingsEntities
     *
     * @return void
     * @throws JsonException
     * @throws RepositoryNotRegisteredException
     */
    private function migrateTeasersData(array $teasers, array $arrayOfWidgetSettingsEntities): void
    {
        foreach ($teasers as $teaser) {
            if (!isset($teaser['store_ids'])) {
                continue;
            }

            $storeIds = explode(',', $teaser['store_ids']);
            /** @var array<string, mixed> $widgetParameters */
            $widgetParameters = json_decode(
                $teaser['widget_parameters'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            /** @var string $priceSelector */
            $priceSelector = $widgetParameters['price_sel'];
            /** @var string $destinationSelector */
            $destinationSelector = $widgetParameters['dest_sel'];

            /** @var string $theme */
            $theme = $widgetParameters['theme'];
            $theme = $this->getValidatedThemeJson($theme);

            /** @var array<string> $paymentMethods */
            $paymentMethods = array_key_exists('payment_methods', $widgetParameters) ?
                $widgetParameters['payment_methods'] : [];
            /** @var array<array<string, mixed>> $teaserPaymentMethods */
            $teaserPaymentMethods = $this->getPaymentMethodsData($paymentMethods);

            if (in_array('0', $storeIds, true)) {
                foreach ($arrayOfWidgetSettingsEntities as $key => $widgetSettingsEntity) {
                    $this->migrateWidgetConfigurationForStore(
                        $key,
                        $priceSelector,
                        $destinationSelector,
                        $theme,
                        $teaserPaymentMethods,
                        $widgetSettingsEntity
                    );
                }

                continue;
            }

            foreach ($storeIds as $storeId) {
                if (!array_key_exists($storeId, $arrayOfWidgetSettingsEntities)) {
                    continue;
                }

                $widgetSettingsEntity = $arrayOfWidgetSettingsEntities[$storeId];
                $this->migrateWidgetConfigurationForStore(
                    $storeId,
                    $priceSelector,
                    $destinationSelector,
                    $theme,
                    $teaserPaymentMethods,
                    $widgetSettingsEntity
                );
            }
        }
    }

    /**
     * Returns mapped value for given theme value
     *
     * @param string $theme
     *
     * @return string
     * @throws JsonException
     */
    private function getValidatedThemeJson(string $theme): string
    {
        // If input is valid JSON and decodes to array, return it as-is
        try {
            $decoded = json_decode($theme, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                return $theme;
            }
        } catch (JsonException $e) {
            Logger::logInfo('Sequra teaser theme is not JSON formatted.');
            // Continue to try mapping if not valid JSON
        }

        // If theme is a known key, return mapped style as JSON
        if (isset(self::WIDGET_STYLE_MAP[$theme])) {
            return json_encode(self::WIDGET_STYLE_MAP[$theme], JSON_THROW_ON_ERROR);
        }

        // Return empty string if neither valid JSON nor known key
        return '';
    }

    /**
     * Decodes payment methods data from teaser payment methods data
     *
     * @param array<string> $paymentMethods
     *
     * @return array<mixed>
     *
     * @throws JsonException
     */
    private function getPaymentMethodsData(array $paymentMethods): array
    {
        return array_map(
            static function ($value) {
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
     * @param mixed[] $teaserPaymentMethods
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
    ): void {
        $availableCountries = $this->getAvailableCountries($storeId);
        $paymentMethods = [];
        foreach ($teaserPaymentMethods as $teaserPaymentMethod) {
            if (is_array($teaserPaymentMethod)
                && isset($teaserPaymentMethod['countryCode'], $teaserPaymentMethod['product'])
                && in_array($teaserPaymentMethod['countryCode'], $availableCountries, true)
                && !in_array($teaserPaymentMethod['product'], $paymentMethods, true)
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
     * @param string[] $paymentMethods
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
    ): void {
        $widgetSettings = $widgetSettingsEntity->getWidgetSettings();
        $widgetSettingsForProduct = $widgetSettings->getWidgetSettingsForProduct();
        if ($widgetSettingsForProduct) {
            $widgetSettingsForProduct = $this->updateExistingWidgetSettingsForProduct(
                $widgetSettingsForProduct,
                $priceSelector,
                $destinationSelector,
                $theme,
                $paymentMethods
            );
        } else {
            $widgetSettingsForProduct = $this->createNewWidgetSettingsForProduct(
                $priceSelector,
                $destinationSelector,
                $theme,
                $paymentMethods
            );
        }

        $widgetSettings->setWidgetSettingsForProduct($widgetSettingsForProduct);
        $widgetSettingsEntity->setWidgetSettings($widgetSettings);
        $this->getWidgetSettingRepository()->update($widgetSettingsEntity);
    }

    /**
     * Updates existing settings for product page
     *
     * @param WidgetSelectorSettings $widgetSettingsForProduct
     * @param string $priceSelector
     * @param string $destinationSelector
     * @param string $theme
     * @param array<string> $paymentMethods
     * @return WidgetSelectorSettings
     */
    private function updateExistingWidgetSettingsForProduct(
        WidgetSelectorSettings $widgetSettingsForProduct,
        string                 $priceSelector,
        string                 $destinationSelector,
        string                 $theme,
        array                  $paymentMethods
    ): WidgetSelectorSettings {
        $widgetSettingsForProduct->setPriceSelector($priceSelector);
        $widgetSettingsForProduct->setLocationSelector($destinationSelector);

        $customWidgetSettings = $widgetSettingsForProduct->getCustomWidgetsSettings();
        $updatedPaymentMethods = [];
        foreach ($customWidgetSettings as $customWidgetSetting) {
            if (in_array($customWidgetSetting->getProduct(), $paymentMethods, true)) {
                $updatedPaymentMethods[] = $customWidgetSetting->getProduct();
                $customWidgetSetting->setCustomWidgetStyle($theme);
                $customWidgetSetting->setCustomLocationSelector($destinationSelector);
            }
        }

        $paymentMethods = array_diff($paymentMethods, $updatedPaymentMethods);
        $newCustomWidgetSettings = $this->createCustomWidgetSettings(
            $paymentMethods,
            $theme,
            $destinationSelector
        );
        $widgetSettingsForProduct->setCustomWidgetsSettings(
            array_merge($customWidgetSettings, $newCustomWidgetSettings)
        );

        return $widgetSettingsForProduct;
    }

    /**
     * Creates new settings for product page
     *
     * @param string $priceSelector
     * @param string $destinationSelector
     * @param string $theme
     * @param array<string> $paymentMethods
     * @return WidgetSelectorSettings
     */
    private function createNewWidgetSettingsForProduct(
        string $priceSelector,
        string $destinationSelector,
        string $theme,
        array  $paymentMethods
    ): WidgetSelectorSettings {
        $widgetSettingsForProduct = new WidgetSelectorSettings(
            $priceSelector,
            $destinationSelector
        );

        $customWidgetSettings = $this->createCustomWidgetSettings(
            $paymentMethods,
            $theme,
            $destinationSelector
        );
        $widgetSettingsForProduct->setCustomWidgetsSettings($customWidgetSettings);

        return $widgetSettingsForProduct;
    }

    /**
     * Creates custom widget settings for given payment methods
     *
     * @param array<string> $paymentMethods
     * @param string $theme
     * @param string $destinationSelector
     * @return array<CustomWidgetsSettings>
     */
    private function createCustomWidgetSettings(
        array  $paymentMethods,
        string $theme,
        string $destinationSelector
    ): array {
        $customWidgetSettings = [];
        foreach ($paymentMethods as $paymentMethod) {
            $customWidgetSettings[] = new CustomWidgetsSettings(
                $destinationSelector,
                $paymentMethod,
                true,
                $theme
            );
        }

        return $customWidgetSettings;
    }

    /**
     * Returns country codes for available countries in store
     *
     * @param string $storeId
     *
     * @return string[]
     *
     * @throws Exception
     */
    private function getAvailableCountries(string $storeId): array
    {
        /** @var CountryConfiguration[]|null $countries */
        $countries = StoreContext::doWithStore($storeId, function () {
            return $this->getCountryConfigurationService()->getCountryConfiguration();
        });

        if (!$countries) {
            return [];
        }

        return array_map(static function ($country) {
            return $country->getCountryCode();
        }, $countries);
    }

    /**
     * Disables widgets for payment methods that were not configured prior to migration.
     *
     * @param array<string,array<SeQuraPaymentMethod>> $arrayOfAvailablePaymentMethodsPerStore
     *
     * @return void
     * @throws RepositoryNotRegisteredException
     */
    private function disableUnconfiguredPaymentMethods(array $arrayOfAvailablePaymentMethodsPerStore): void
    {
        $widgetSettingsEntities = $this->getAllWidgetSettingsEntities();
        foreach ($widgetSettingsEntities as $widgetSettingsEntity) {
            $storeId = $widgetSettingsEntity->getStoreId();
            $paymentMethods = [];
            $widgetSettingsForProduct = $widgetSettingsEntity->getWidgetSettings()->getWidgetSettingsForProduct();
            if ($widgetSettingsForProduct) {
                $customWidgetSettings = $widgetSettingsForProduct->getCustomWidgetsSettings();
                foreach ($customWidgetSettings as $customPaymentMethodConfig) {
                    $paymentMethods[] = $customPaymentMethodConfig->getProduct();
                }
            } else {
                $customWidgetSettings = [];
                $widgetSettingsForProduct = new WidgetSelectorSettings('', '');
            }

            $disabledPaymentMethods = [];
            foreach ($arrayOfAvailablePaymentMethodsPerStore[$storeId] as $availablePaymentMethod) {
                $product = $availablePaymentMethod->getProduct();
                if (!in_array($product, $paymentMethods, true) &&
                    !in_array($product, $disabledPaymentMethods, true) &&
                    in_array(
                        $availablePaymentMethod->getCategory(),
                        WidgetSettingsService::WIDGET_SUPPORTED_CATEGORIES_ON_PRODUCT_PAGE,
                        true
                    )
                ) {
                    $disabledPaymentMethods[] = $product;
                    $customWidgetSettings[] = new CustomWidgetsSettings(
                        '',
                        $product,
                        false,
                        ''
                    );
                }
            }

            $widgetSettingsForProduct->setCustomWidgetsSettings($customWidgetSettings);
            $widgetSettingsEntity->getWidgetSettings()->setWidgetSettingsForProduct(
                $widgetSettingsForProduct
            );
            $this->getWidgetSettingRepository()->update($widgetSettingsEntity);
        }
    }

    /**
     * Returns all widget settings entities
     *
     * @return WidgetSettingsEntity[]
     *
     * @throws RepositoryNotRegisteredException
     */
    private function getAllWidgetSettingsEntities(): array
    {
        /** @var WidgetSettingsEntity[] $entities */
        $entities = $this->getWidgetSettingRepository()->select();

        return $entities;
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
