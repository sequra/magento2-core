<?php

namespace Sequra\Core\Model\Config;

use Magento\Payment\Gateway\ConfigInterface;

/**
 * Class PaymentMethodStatus
 *
 * Tells whether the SeQura payment method is active in a store.
 */
class PaymentMethodStatus
{
    /**
     * @var ConfigInterface
     */
    private ConfigInterface $gatewayConfig;

    /**
     * @param ConfigInterface $gatewayConfig
     */
    public function __construct(ConfigInterface $gatewayConfig)
    {
        $this->gatewayConfig = $gatewayConfig;
    }

    /**
     * Whether the payment method is active in the given store, or the current one when none is given.
     *
     * @param int|string|null $storeId
     *
     * @return bool
     */
    public function isActive($storeId = null): bool
    {
        return (bool)$this->gatewayConfig->getValue('active', $storeId === null ? null : (int)$storeId);
    }
}
