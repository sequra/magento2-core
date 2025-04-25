<?php
namespace Sequra\Core\Gateway\Validator;

use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;

class ProductListingWidgetAvailabilityValidator extends ProductWidgetAvailabilityValidator
{

    /**
     * @inheritdoc
     */
    protected function getActionNames()
    {
        return null;
    }

     /**
      * Check if the option is enabled in the settings
      *
      * @param WidgetSettings $widgetSettings
      * @return bool
      */
    protected function isEnabledInSettings($widgetSettings): bool
    {
        return $widgetSettings->isShowInstallmentsInProductListing();
    }
}
