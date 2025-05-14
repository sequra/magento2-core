<?php

namespace Sequra\Core\Model\Api\Builders;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Magento\Framework\DB\Adapter\SqlVersionProvider;
use Magento\Framework\Exception\NoSuchEntityException;
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
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\CreateOrderRequest;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Item\ItemType;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Services\BusinessLogic\ProductService;
use Throwable;

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

    /**
     * Constructor for CreateOrderRequestBuilder
     *
     * @param CartRepositoryInterface $quoteRepository
     * @param ProductMetadataInterface $productMetadata
     * @param ResourceInterface $moduleResource
     * @param DeploymentConfig $deploymentConfig
     * @param SqlVersionProvider $sqlVersionProvider
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param string $cartId
     * @param string $storeId
     * @param ProductService $productService
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        ProductMetadataInterface $productMetadata,
        ResourceInterface $moduleResource,
        DeploymentConfig $deploymentConfig,
        SqlVersionProvider $sqlVersionProvider,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        string $cartId,
        string $storeId,
        ProductService $productService,
        OrderFactory $orderFactory
    ) {
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

    /**
     * Builds the CreateOrderRequest object
     *
     * @return CreateOrderRequest The order request object
     * @throws NoSuchEntityException If the quote cannot be found
     */
    public function build(): CreateOrderRequest
    {
        try {
            // TODO: Property $quote (Magento\Quote\Model\Quote) does not accept Magento\Quote\Api\Data\CartInterface.
            // @phpstan-ignore-next-line
            $this->quote = $this->quoteRepository->getActive((int) $this->cartId);
        } catch (NoSuchEntityException $e) {
            // TODO: Property $quote (Magento\Quote\Model\Quote) does not accept Magento\Quote\Api\Data\CartInterface.
            // @phpstan-ignore-next-line
            $this->quote = $this->quoteRepository->get((int) $this->cartId);
        }

        return $this->generateCreateOrderRequest();
    }

    /**
     * Returns true if SeQura payment methods are available for current checkout. Otherwise it returns false.
     *
     * @param GeneralSettingsResponse $generalSettingsResponse
     *
     * @return bool
     */
    public function isAllowedFor(GeneralSettingsResponse $generalSettingsResponse): bool
    {
        try {
            $generalSettings = $generalSettingsResponse->toArray();
            // TODO: Property $quote (Magento\Quote\Model\Quote) does not accept Magento\Quote\Api\Data\CartInterface.
            // @phpstan-ignore-next-line
            $this->quote = $this->quoteRepository->getActive((int) $this->cartId);
            $merchantId = $this->getMerchantId();

            if (!$merchantId) {
                return false;
            }

            if (!empty($generalSettings['allowedIPAddresses']) &&
                !empty($ipAddress = $this->getCustomerIpAddress()) &&
                is_array($generalSettings['allowedIPAddresses']) &&
                !in_array($ipAddress, $generalSettings['allowedIPAddresses'], true)
            ) {
                return false;
            }

            if (empty($generalSettings['excludedProducts']) &&
                empty($generalSettings['excludedCategories'])
            ) {
                return true;
            }

            // TODO: Property $quote (Magento\Quote\Model\Quote) does not accept Magento\Quote\Api\Data\CartInterface.
            // @phpstan-ignore-next-line
            $this->quote = $this->quoteRepository->getActive((int) $this->cartId);
            // TODO: Call to an undefined method Magento\Quote\Api\Data\CartInterface::getAllVisibleItems().
            // @phpstan-ignore-next-line
            foreach ($this->quote->getAllVisibleItems() as $item) {
                if (!empty($generalSettings['excludedProducts']) &&
                    !empty($item->getSku()) &&
                    (in_array($item->getProduct()->getData('sku'), $generalSettings['excludedProducts'], true) ||
                        in_array($item->getProduct()->getSku(), $generalSettings['excludedProducts'], true))
                ) {
                    return false;
                }

                if ($item->getIsVirtual()) {
                    return false;
                }

                if (!empty($generalSettings['excludedCategories']) &&
                    !empty(array_intersect(
                        $generalSettings['excludedCategories'],
                        $this->productService->getAllProductCategoryIds($item->getProduct()->getCategoryIds())
                    ))
                ) {
                    return false;
                }
            }

            return true;
        } catch (Throwable $exception) {
            Logger::logWarning('Unexpected error occurred while checking if SeQura payment methods are available.
             Reason: ' . $exception->getMessage() . ' . Stack trace: ' . $exception->getTraceAsString());

            return false;
        }
    }

    /**
     * Generates the CreateOrderRequest object with all necessary data
     *
     * @return CreateOrderRequest Fully populated order request
     */
    private function generateCreateOrderRequest(): CreateOrderRequest
    {
        //@var string
        $shippingMethod = $this->quote->getShippingAddress()->getShippingMethod();
        return CreateOrderRequest::fromArray([
            'state' => '',
            'merchant' => $this->getMerchantData(),
            'cart' => $this->getCart(),
            'delivery_method' => [
                'name' => $shippingMethod,
                'home_delivery' => !in_array(
                    $shippingMethod,
                    [
                        //Magento MSI In-Store Pickup (BOPIS)
                        'msi_instore_pickup',
                        'instore_pickup',
                        //Amasty Store Pickup
                        'amstorepickup_amstorepickup',
                        'amstorepickup_storepickup',
                        //Mageplaza Store Pickup
                        'mageplaza_storepickup',
                        //Mirasvit Store Pickup
                        'mirasvit_pickup',
                        'mirasvit_storepickup',
                        //MageWorx Store Pickup
                        'mageworx_storepickup',
                        'mageworx_instore_pickup',
                        //Webkul Store Pickup
                        'webkul_storepickup',
                        //Other/Generic or Custom Store Pickup
                        'storepickup_storepickup',
                        'pickup_storepickup',
                        'clickandcollect_clickandcollect',
                        'instorepickup_instorepickup',
                        'pickup_pickup',
                        'clickandcollect',
                        'pick_instore',
                        'pickup',
                    ]
                ),
            ],
            'delivery_address' => $this->getAddress($this->quote->getShippingAddress()),
            'invoice_address' => $this->getAddress($this->quote->getBillingAddress()),
            'customer' => $this->getCustomer(),
            'gui' => [
                'layout' => 'desktop',
            ],
            'platform' => $this->getPlatform()
        ]);
    }

    /**
     * Gets merchant data for the order request
     *
     * @return array<string, mixed> Merchant information including URLs and identification
     */
    private function getMerchantData(): array
    {
        $signature = $this->getSignature();
        $webhookUrl = $this->urlBuilder->getUrl('sequra/webhook');

        // Only for development environment. Replace local shop domain with ngrok.
        if (defined('SEQURA_NGROK_URL') && !empty(SEQURA_NGROK_URL)) {
            // TODO: The use of function parse_url() is discouraged
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $localShopDomain = parse_url($webhookUrl, PHP_URL_HOST);
            $webhookUrl = str_replace(
                ['http://', 'https://', $localShopDomain],
                ['', '', SEQURA_NGROK_URL],
                $webhookUrl
            );
        }

        return [
            'id' => (string)$this->getMerchantId(),
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

    /**
     * Gets cart data for the order request
     *
     * @return array<string, mixed> Cart information including totals, currency and items
     */
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

    /**
     * Gets all order items including products, shipping and discounts
     *
     * @return array<int, mixed> Array of order items
     */
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

    /**
     * Calculates the total discount amount for the order
     *
     * @return int Total discount amount in cents (negative value)
     */
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

    /**
     * Formats an address into the required format for Sequra
     *
     * @param Address $address The Magento address object
     *
     * @return array<string, mixed> Formatted address data
     */
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

    /**
     * Gets customer information for the order request
     *
     * @return array<string, mixed> Customer details including personal information and order history
     */
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
            // TODO: Direct use of $_SERVER Superglobal detected.
            // phpcs:ignore Magento2.Security.Superglobal.SuperglobalUsageWarning
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
     * Get previous orders for a customer
     *
     * @param int $customerId
     *
     * @return array<array<string, mixed>> Array of previous orders
     */
    private function getPreviousOrders($customerId): array
    {
        $orderModel = $this->orderFactory->create();
        $orderCollection = $orderModel->getCollection()->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $orders = [];

        if (!$orderCollection) {
            return $orders;
        }

        /**
         * @var Order $orderRow
         */
        foreach ($orderCollection as $orderRow) {
            $order = [];
            $order['amount'] = $this->formatPrice(is_numeric($orderRow->getData('grand_total'))
                ? (float) $orderRow->getData('grand_total') : 0);
            $order['currency'] = $orderRow->getData('order_currency_code');
            $createdAt = $orderRow->getData('created_at');
            $order['created_at'] = str_replace(' ', 'T', is_string($createdAt) ? $createdAt : '');
            $order['raw_status'] = $orderRow->getData('status');
            $billingAddress = $orderRow->getBillingAddress();
            $order['postal_code'] = $billingAddress ? $billingAddress->getPostCode() : '';
            $order['country_code'] = $billingAddress ? $billingAddress->getCountryId() : '';
            $order['status'] = $this->mapOrderStatus(is_string($order['raw_status']) ? $order['raw_status'] : '');
            $payment = $orderRow->getPayment();
            $order['payment_method_raw'] = $payment ? $payment->getAdditionalInformation()['method_title'] : '';
            $order['payment_method'] = $payment ? $this->mapPaymentName($payment->getMethod()) : '';

            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * Maps the payment method name to a Sequra-compatible format
     *
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
     * Maps the order status to a Sequra-compatible format
     *
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
     * Formats the price to an integer value in cents
     *
     * @param float $price
     *
     * @return int
     */
    private function formatPrice($price): int
    {
        if (!is_numeric($price)) {
            return 0;
        }

        return (int) round(100 * $price);
    }

    /**
     * Gets platform information for the order request
     *
     * @return array<string, string> Platform details including version information
     */
    private function getPlatform(): array
    {
        $connectionData = $this->deploymentConfig->get(
            ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTION_DEFAULT,
            []
        );

        return [
            'name' => 'magento2',
            'version' => (string) $this->productMetadata->getVersion(),
            'plugin_version' => (string) $this->moduleResource->getDbVersion('Sequra_Core'),
            'uname' => php_uname(),
            'db_name' => is_array($connectionData) && !empty($connectionData['model'])
            && is_string($connectionData['model']) ? $connectionData['model'] : 'mysql',
            'db_version' => (string) $this->sqlVersionProvider->getSqlVersion(),
            'php_version' => (string) phpversion(),
        ];
    }

    /**
     * Get merchant ID based on the shipping country
     *
     * @return string|null
     */
    private function getMerchantId(): ?string
    {
        $shippingCountry = $this->quote->getShippingAddress()->getCountryId();
        // @phpstan-ignore-next-line
        $data = AdminAPI::get()->countryConfiguration($this->storeId)->getCountryConfigurations();
        if (!$data->isSuccessful()) {
            return null;
        }

        $merchantId = null;
        foreach ($data->toArray() as $country) {
            if ($country['countryCode'] === $shippingCountry && !empty($country['merchantId'])) {
                $merchantId = $country['merchantId'];
            }
        }

        return $merchantId;
    }

    /**
     * Gets the signature for secure communication with Sequra
     *
     * @return string HMAC signature
     * @throws \RuntimeException If merchant configuration cannot be found
     */
    private function getSignature(): string
    {
        // @phpstan-ignore-next-line
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

    /**
     * Gets the customer's IP address from server variables
     *
     * @return string IP address
     */
    private function getCustomerIpAddress(): string
    {
        // TODO: Direct use of $_SERVER Superglobal detected
        // phpcs:disable Magento2.Security.Superglobal.SuperglobalUsageWarning
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'];
        // phpcs:enable Magento2.Security.Superglobal.SuperglobalUsageWarning
    }
}
