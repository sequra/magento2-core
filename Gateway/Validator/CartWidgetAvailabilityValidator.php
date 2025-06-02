<?php
namespace Sequra\Core\Gateway\Validator;

class CartWidgetAvailabilityValidator extends AbstractWidgetAvailabilityValidator
{

    /**
     * @inheritdoc
     */
    protected function getActionNames()
    {
        return ['checkout_cart_index'];
    }

    /**
     * @inheritdoc
     */
    protected function getValidationResult(array $validationSubject)
    {
        if (!parent::getValidationResult($validationSubject)) {
            return false;
        }
        $storeId = (string) $validationSubject['storeId'];
        $widgetSettings = $this->getWidgetSettings($storeId);

        if (empty($widgetSettings) || !$widgetSettings->isShowInstallmentsInCartPage()) {
            return false;
        }
        return true;
    }
}
