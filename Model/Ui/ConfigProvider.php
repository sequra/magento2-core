<?php

namespace Sequra\Core\Model\Ui;

use Exception;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\GeneralSettings\Responses\GeneralSettingsResponse;
use Sequra\Core\Model\Config\PaymentMethodStatus;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'sequra_payment';

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var UrlInterface
     */
    private $urlBuilder;
    /**
     * @var PaymentMethodStatus
     */
    private PaymentMethodStatus $paymentMethodStatus;

    /**
     * ConfigProvider constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param PaymentMethodStatus $paymentMethodStatus
     */
    public function __construct(
        StoreManagerInterface                         $storeManager,
        UrlInterface $urlBuilder,
        PaymentMethodStatus $paymentMethodStatus
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->paymentMethodStatus = $paymentMethodStatus;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @throws NoSuchEntityException
     * @throws Exception
     *
     * @phpstan-return array<string, array<string, array<string, mixed>>>
     * @return array
     */
    public function getConfig()
    {
        if (!$this->paymentMethodStatus->isActive()) {
            return [];
        }

        $currentStore = $this->storeManager->getStore();
        $storeId = (string) $currentStore->getId();
        /** @var GeneralSettingsResponse $generalSettingsResponse */
        $generalSettingsResponse = AdminAPI::get()->generalSettings($storeId)->getGeneralSettings();
        $showFormAsHostedPage = false;
        if ($generalSettingsResponse->isSuccessful()) {
            $showFormAsHostedPage = $generalSettingsResponse->toArray()['showSeQuraCheckoutAsHostedPage'] ?? false;
        }

        return [
            'payment' => [
                self::CODE => [
                    'showlogo' => true,
                    'showSeQuraCheckoutAsHostedPage' => $showFormAsHostedPage,
                    'sequraCheckoutHostedPage' => $this->urlBuilder->getUrl('sequra/hpp'),
                ]
            ]
        ];
    }
}
