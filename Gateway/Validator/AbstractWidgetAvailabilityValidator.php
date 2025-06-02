<?php
namespace Sequra\Core\Gateway\Validator;

use Exception;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Models\WidgetSettings;
use SeQura\Core\BusinessLogic\Domain\PromotionalWidgets\Services\WidgetSettingsService;
use SeQura\Core\Infrastructure\ServiceRegister;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

abstract class AbstractWidgetAvailabilityValidator extends AbstractValidator
{

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * Constructor
     *
     * @param ResultInterfaceFactory $resultFactory
     * @param Http $request
     */
    public function __construct(ResultInterfaceFactory $resultFactory, Http $request)
    {
        parent::__construct($resultFactory);
        $this->request = $request;
    }

    /**
     * Get the compatible action names for showing the widget or null to skip validation
     *
     * @return array<string>|null
     */
    protected function getActionNames()
    {
        return null;
    }

    /**
     * Validate if the widget is enabled for the product
     *
     * @param array $validationSubject
     * @phpstan-param array<string, string|int> $validationSubject
     *
     * @throws \Throwable
     *
     * @return bool
     */
    protected function getValidationResult(array $validationSubject)
    {
        if (!isset($validationSubject['storeId']) || (
            is_array($this->getActionNames()) &&
            !in_array($this->request->getFullActionName(), $this->getActionNames(), true)
        )) {
            return false;
        }

        $storeId = (string) $validationSubject['storeId'];

        $widgetSettings = $this->getWidgetSettings($storeId);

        if (empty($widgetSettings) || !$widgetSettings->isEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @param array $validationSubject
     * @phpstan-param array<string, string|int> $validationSubject
     */
    public function validate(array $validationSubject)
    {
        try {
            return $this->createResult($this->getValidationResult($validationSubject));
        } catch (\Throwable $e) {
            return $this->createResult(false);
        }
    }

    /**
     * Get widget settings for the given store ID
     *
     * @param string $storeId The store ID for which to get widget settings
     *
     * @return WidgetSettings|null
     */
    protected function getWidgetSettings($storeId)
    {
        /**
         * @var WidgetSettings|null $settings
         */
        $settings = StoreContext::doWithStore($storeId, function () {
            return ServiceRegister::getService(WidgetSettingsService::class)->getWidgetSettings();
        });
        return $settings;
    }

    /**
     * Get general settings for the given store ID
     *
     * @param string $storeId The store ID for which to get general settings
     *
     * @return GeneralSettings|null
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    protected function getGeneralSettings($storeId): ?GeneralSettings
    {
        /**
         * @var GeneralSettings|null $settings
         */
        $settings = StoreContext::doWithStore($storeId, function () {
            return ServiceRegister::getService(GeneralSettingsService::class)->getGeneralSettings();
        });
        return $settings;
    }
}
