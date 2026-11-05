<?php

namespace Sequra\Core\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Sequra\Core\Model\Ui\ConfigProvider;

/**
 * Class PaymentMethodStatus
 *
 * Tells whether the SeQura payment method is active in a store.
 */
class PaymentMethodStatus
{
    private const XML_PATH_ACTIVE = 'payment/' . ConfigProvider::CODE . '/active';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
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
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
