<?php
namespace Sequra\Core\Gateway\Validator;

use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\GeneralSettings\Responses\GeneralSettingsResponse;

class IpAddressValidator extends \Magento\Payment\Gateway\Validator\AbstractValidator
{
    /**
     * @inheritdoc
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;

        /** @var GeneralSettingsResponse $settings */
        $settings = AdminAPI::get()->generalSettings($validationSubject['storeId'])->getGeneralSettings();

        if (!$settings->isSuccessful()) {
            return $this->createResult(false);
        }

        $allowedIPAddresses = $settings->toArray()['allowedIPAddresses'] ?? [];

        if (!empty($allowedIPAddresses) && is_array($allowedIPAddresses)) {
            $ipAddress = $this->getCustomerIpAddress();
            $isValid = in_array($ipAddress, $allowedIPAddresses, true);
        }

        return $this->createResult($isValid);
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
