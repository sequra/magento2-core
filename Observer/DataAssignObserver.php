<?php

namespace Sequra\Core\Observer;

use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class DataAssignObserver
 *
 * @package Sequra\Core\Observer
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
    const SEQURA_PRODUCT_KEY = 'sequra_product';
    const SEQURA_CAMPAIGN_KEY = 'sequra_campaign';

    /**
     * @var array
     */
    protected $additionalInformationList = [
        self::SEQURA_PRODUCT_KEY,
        self::SEQURA_CAMPAIGN_KEY,
    ];

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
