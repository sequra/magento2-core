<?php
namespace Sequra\Core\Gateway\Validator;

use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Models\GeneralSettings;
use SeQura\Core\BusinessLogic\Domain\GeneralSettings\Services\GeneralSettingsService;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;

class IpAddressValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{
    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;
        $settings = null;
        try {
            $settings = StoreContext::doWithStore($validationSubject['storeId'], function () {
                $g = $this->getGeneralSettings();
                return $g;
            });
		} catch ( \Throwable $e ) {
            return $this->createResult(false);
		}

        $allowedIPAddresses = $settings->getAllowedIPAddresses();
        if (!empty($allowedIPAddresses) && is_array($allowedIPAddresses)) {
            $ipAddress = $this->getCustomerIpAddress();
            $isValid = in_array($ipAddress, $allowedIPAddresses, true);
        }
        
        return $this->createResult($isValid);
    }

    /**
     * @return GeneralSettings|null
     *
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function getGeneralSettings(): ?GeneralSettings
    {
        $settingsService = ServiceRegister::getService(GeneralSettingsService::class);

        return $settingsService->getGeneralSettings();
    }

    private function getCustomerIpAddress(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
    }
}
