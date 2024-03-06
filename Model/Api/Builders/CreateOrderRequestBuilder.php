<?php

namespace Sequra\Core\Model\Api\Builders;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\DB\Adapter\SqlVersionProvider;
use Magento\Framework\Module\ResourceInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote as QuoteEntity;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Config;
use SeQura\Core\BusinessLogic\AdminAPI\AdminAPI;
use SeQura\Core\BusinessLogic\AdminAPI\GeneralSettings\Responses\GeneralSettingsResponse;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\CreateOrderRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Item\ItemType;
use SeQura\Core\BusinessLogic\Domain\UIState\Services\UIStateService;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\ProductService;

/**
 * Class CreateOrderRequestBuilder
 *
 * @package Sequra\Core\Model\Api\Builders
 */
class CreateOrderRequestBuilder implements \SeQura\Core\BusinessLogic\Domain\Order\Builders\CreateOrderRequestBuilder
{
    /**
     * @var string
     */
    private $cartId;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var QuoteEntity
     */
    private $quote;
    /**
     * @var string
     */
    private $storeId;
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;
    /**
     * @var ResourceInterface
     */
    private $moduleResource;
    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;
    /**
     * @var SqlVersionProvider
     */
    private $sqlVersionProvider;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var UrlInterface
     */
    private $urlBuilder;
    /**
     * @var ProductService
     */
    private $productService;
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    public function __construct(
        CartRepositoryInterface  $quoteRepository,
        ProductMetadataInterface $productMetadata,
        ResourceInterface        $moduleResource,
        DeploymentConfig         $deploymentConfig,
        SqlVersionProvider       $sqlVersionProvider,
        ScopeConfigInterface     $scopeConfig,
        UrlInterface             $urlBuilder,
        string                   $cartId,
        string                   $storeId,
        ProductService           $productService,
        OrderFactory             $orderFactory
    )
    {
        $this->quoteRepository = $quoteRepository;
        $this->productMetadata = $productMetadata;
        $this->moduleResource = $moduleResource;
        $this->deploymentConfig = $deploymentConfig;
        $this->sqlVersionProvider = $sqlVersionProvider;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->cartId = $cartId;
        $this->storeId = $storeId;
        $this->productService = $productService;
        $this->orderFactory = $orderFactory;
    }

    public function build(): CreateOrderRequest
    {
        $this->quote = $this->quoteRepository->getActive($this->cartId);

        return $this->generateCreateOrderRequest();
    }

    public function isAllowedFor(GeneralSettingsResponse $generalSettingsResponse): bool
    {
        $generalSettings = $generalSettingsResponse->toArray();
        $stateService = ServiceRegister::getService(UIStateService::class);
        $isOnboarding = StoreContext::doWithStore($this->storeId, [$stateService, 'isOnboardingState']);

        if ($isOnboarding) {
            return false;
        }

        if (
            !empty($generalSettings['allowedIPAddresses']) &&
            !empty($ipAddress = $this->getCustomerIpAddress()) &&
            !in_array($ipAddress, $generalSettings['allowedIPAddresses'], true)
        ) {
            return false;
        }

        if (
            empty($generalSettings['excludedProducts']) &&
            empty($generalSettings['excludedCategories'])
        ) {
            return true;
        }

        $this->quote = $this->quoteRepository->getActive($this->cartId);
        foreach ($this->quote->getAllVisibleItems() as $item) {
            if (
                !empty($generalSettings['excludedProducts']) &&
                !empty($item->getSku()) &&
                (in_array($item->getProduct()->getData('sku'), $generalSettings['excludedProducts'], true) ||
                    in_array($item->getProduct()->getSku(), $generalSettings['excludedProducts'], true))
            ) {
                return false;
            }

            if ($item->getIsVirtual()) {
                return false;
            }

            if (
                !empty($generalSettings['excludedCategories']) &&
                !empty(array_intersect($generalSettings['excludedCategories'],
                    $this->productService->getAllProductCategories($item->getProduct()->getCategoryIds())))
            ) {
                return false;
            }
        }

        return true;
    }

    private function generateCreateOrderRequest(): CreateOrderRequest
    {
        return CreateOrderRequest::fromArray([
            'state' => '',
            'merchant' => $this->getMerchantData(),
//            'merchant_reference' => [
//                'order_ref_1' => $request->getOrder()->getId(),
//            ],
            'cart' => $this->getCart(),
            'delivery_method' => [
                'name' => $this->quote->getShippingAddress()->getShippingMethod(),
            ],
            'delivery_address' => $this->getAddress($this->quote->getShippingAddress()),
            'invoice_address' => $this->getAddress($this->quote->getBillingAddress()),
            'customer' => $this->getCustomer(),
//            'instore' => [
//                'code' => $request->getOrder()->getId(),
//            ],
            'gui' => [
                'layout' => 'desktop',
            ],
            'platform' => $this->getPlatform()
        ]);
    }

    private function getMerchantData(): array
    {
        $signature = $this->getSignature();
        $webhookUrl = $this->urlBuilder->getUrl('sequra/webhook');

        // Only for development environment. Replace local shop domain with ngrok.
        if (defined('SEQURA_NGROK_URL') && !empty(SEQURA_NGROK_URL)) {
            $localShopDomain = parse_url($webhookUrl, PHP_URL_HOST);
            $webhookUrl = str_replace(
                ['http://', 'https://', $localShopDomain],
                ['', '', SEQURA_NGROK_URL],
                $webhookUrl
            );
        }

        return [
            'id' => $this->getMerchantId(),
            'notify_url' => $webhookUrl,
            'return_url' => $this->urlBuilder->getUrl('sequra/comeback', ['cartId' => $this->cartId]),
            'notification_parameters' => [
                'cartId' => $this->cartId,
                'signature' => $signature,
                'storeId' => $this->storeId,
            ],
            'events_webhook' => [
                'url' => $webhookUrl,
                'parameters' => [
                    'cartId' => $this->cartId,
                    'signature' => $signature,
                    'storeId' => $this->storeId,
                ],
            ],
        ];
    }

    private function getCart(): array
    {
        return [
            'currency' => $this->quote->getQuoteCurrencyCode(),
            'gift' => false,
            'order_total_with_tax' => (int)round(100 * $this->quote->getGrandTotal()),
            'cart_ref' => $this->cartId,
            'created_at' => $this->quote->getCreatedAt(),
            'updated_at' => $this->quote->getUpdatedAt(),
            'items' => $this->getOrderItems()
        ];
    }

    private function getOrderItems(): array
    {
        $items = [];

        foreach ($this->quote->getAllVisibleItems() as $item) {
            $items[] = [
                'type' => ItemType::TYPE_PRODUCT,
                'reference' => $item->getSku(),
                'name' => $item->getName(),
                'price_with_tax' => (int)round(100 * $item->getPriceInclTax()),
                'quantity' => $item->getQty(),
                'total_with_tax' => (int)round(100 * $item->getRowTotalInclTax()),
                'downloadable' => (bool)$item->getIsVirtual(),
                'description' => $item->getDescription(),
                'category' => $item->getProduct()->getCategory() ? $item->getProduct()->getCategory()->getName() : '',
                'product_id' => $item->getProduct()->getId(),
                'url' => $item->getProduct()->getProductUrl(),
            ];
        }

        if ($this->quote->getShippingAddress()->getAllVisibleItems() !== null) {
            $items[] = [
                'type' => 'handling',
                'reference' => 'shipping cost',
                'name' => 'Shipping cost',
                'total_with_tax' => (int)round(100 * $this->quote->getShippingAddress()->getShippingInclTax()),
            ];
        }

        $discount = $this->getTotalDiscountAmount();
        if ($discount < 0) {
            $items[] = [
                'type' => 'discount',
                'reference' => 'discount',
                'name' => 'Discount',
                'total_with_tax' => $discount,
            ];
        }

        return $items;
    }

    private function getTotalDiscountAmount(): int
    {
        $totalDiscount = 0;

        $taxAfterDiscount = $this->scopeConfig->getValue(
            Config::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT,
            ScopeInterface::SCOPE_STORE,
            $this->storeId
        );

        $pricesIncludeTax = $this->scopeConfig->getValue(
            Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORE,
            $this->storeId
        );

        /** @var QuoteEntity\Item $item */
        foreach ($this->quote->getAllItems() as $item) {
            $discount = $item->getDiscountAmount();

            // Needed because of tax difference on SeQura and Magento
            if ($taxAfterDiscount && !$pricesIncludeTax) {
                $discount *= (1 + $item->getTaxPercent() / 100);
            }

            $totalDiscount += (int)round(100 * $discount);
        }

        return -1 * $totalDiscount;
    }

    private function getAddress(Address $address): array
    {
        return [
            'given_names' => $address->getFirstname(),
            'surnames' => $address->getLastname(),
            'company' => $address->getCompany(),
            'address_line_1' => $address->getStreetFull(),
            'address_line_2' => '',
            'postal_code' => $address->getPostcode(),
            'city' => $address->getCity(),
            'country_code' => $address->getCountryId(),
            'phone' => $address->getTelephone(),
            'state' => $address->getRegion(),
        ];
    }

    private function getCustomer(): array
    {
        $email = $this->quote->getCustomer()->getEmail();
        if (empty($email)) {
            $email = $this->quote->getBillingAddress()->getEmail();
        }
        if (empty($email)) {
            $email = $this->quote->getShippingAddress()->getEmail();
        }

        return [
            'given_names' => $this->quote->getCustomer()->getFirstname(),
            'surnames' => $this->quote->getCustomer()->getLastname(),
            'email' => $email,
            'logged_in' => !$this->quote->getCustomerIsGuest(),
            'language_code' => $this->quote->getStore()->getConfig('general/locale/code'),
            'ip_number' => $this->getCustomerIpAddress(),
            'user_agent' => $_SERVER["HTTP_USER_AGENT"],
            'date_of_birth' => $this->quote->getCustomer()->getDob(),
            'company' => $this->quote->getBillingAddress()->getCompany(),
            'vat_number' => $this->quote->getBillingAddress()->getVatId(),
            'created_at' => $this->quote->getCustomer()->getCreatedAt(),
            'updated_at' => $this->quote->getCustomer()->getUpdatedAt(),
            'previous_orders' => $this->getPreviousOrders($this->quote->getCustomer()->getId()),
        ];
    }

    /**
     * @param $customerId
     *
     * @return array
     */
    private function getPreviousOrders($customerId): array
    {
        $orderModel = $this->orderFactory->create();
        $orderCollection = $orderModel->getCollection()->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $orders = [];

        if (!$orderCollection) {
            return $orders;
        }

        foreach ($orderCollection as $orderRow) {
            $order = [];
            $order['amount'] = $this->formatPrice($orderRow->getData('grand_total'));
            $order['currency'] = $orderRow->getData('order_currency_code');
            $order['created_at'] = str_replace(' ', 'T', $orderRow->getData('created_at'));
            $order['raw_status'] = $orderRow->getData('status');
            $order['postal_code'] = $orderRow->getBillingAddress()->getPostCode();
            $order['country_code'] = $orderRow->getBillingAddress()->getCountryId();
            $order['status'] = $this->mapOrderStatus($orderRow->getData('status'));
            $order['payment_method_raw'] = $orderRow->getPayment()->getAdditionalInformation()['method_title'] ?? '';
            $order['payment_method'] = $this->mapPaymentName($orderRow->getPayment()->getMethod());

            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function mapPaymentName(string $name): string
    {
        if (str_contains($name, 'card')) {
            return 'CC';
        }

        if (str_contains($name, 'paypal')) {
            return 'PP';
        }

        if ($name === 'banktransfer') {
            return 'TR';
        }

        if ($name === 'cashondelivery') {
            return 'COD';
        }

        if (str_contains($name, 'sequra')) {
            return 'SQ';
        }

        return 'O/' . $name;
    }

    /**
     * @param string $magentoStatus
     *
     * @return string
     */
    private function mapOrderStatus(string $magentoStatus): string
    {
        switch ($magentoStatus) {
            case Order::STATE_COMPLETE:
                return 'shipped';
            case Order::STATE_CANCELED:
                return 'cancelled';
        }

        return 'processing';
    }

    /**
     * @param $price
     *
     * @return int
     */
    private function formatPrice($price): int
    {
        if (!is_numeric($price)) {
            return 0;
        }

        return intval(round(100 * $price));
    }

    private function getPlatform(): array
    {
        $connectionData = $this->deploymentConfig->get(
            ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTION_DEFAULT, []
        );

        return [
            'name' => 'magento2',
            'version' => $this->productMetadata->getVersion(),
            'plugin_version' => $this->moduleResource->getDbVersion('Sequra_Core'),
            'uname' => php_uname(),
            'db_name' => !empty($connectionData['model']) ? $connectionData['model'] : 'mysql',
            'db_version' => $this->sqlVersionProvider->getSqlVersion(),
            'php_version' => phpversion(),
        ];
    }

    /**
     * @return string
     */
    private function getMerchantId(): string
    {
        $shippingCountry = $this->quote->getShippingAddress()->getCountryId();
        $data = AdminAPI::get()->countryConfiguration($this->storeId)->getCountryConfigurations();
        if (!$data->isSuccessful()) {
            throw new \RuntimeException('Unable to find merchant configuration for selling country ' . $shippingCountry);
        }

        $merchantId = null;
        foreach ($data->toArray() as $country) {
            if ($country['countryCode'] === $shippingCountry && !empty($country['merchantId'])) {
                $merchantId = $country['merchantId'];
            }
        }

        if (!$merchantId) {
            throw new \RuntimeException('Unable to find merchant configuration for selling country ' . $shippingCountry);
        }

        return (string)$merchantId;
    }

    private function getSignature(): string
    {
        $data = AdminAPI::get()->connection($this->storeId)->getConnectionSettings();
        if (!$data->isSuccessful()) {
            throw new \RuntimeException('Unable to find merchant configuration');
        }

        $connectionData = $data->toArray();
        return hash_hmac(
            'sha256',
            implode('_', [$this->cartId, $connectionData['merchantId'], $connectionData['username']]),
            $connectionData['password']
        );
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
