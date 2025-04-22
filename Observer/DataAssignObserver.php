<?php

namespace Sequra\Core\Observer;

use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\PaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    public const SEQURA_PRODUCT_KEY = 'sequra_product';
    public const SEQURA_CAMPAIGN_KEY = 'sequra_campaign';

    /**
     * @var string[]
     */
    protected $additionalInformationList = [
        self::SEQURA_PRODUCT_KEY,
        self::SEQURA_CAMPAIGN_KEY,
    ];

    /**
     * Observes execution of payment data assignment
     *
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
