<?php

namespace Sequra\Core\Block\ExpressCheckout;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPage;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Block\WidgetTrait;
use Sequra\Core\Model\ExpressCheckout\AvailabilityEvaluator;

/**
 * Class ProductPage
 *
 * Renders the SeQura Express Checkout button on the product detail page. Unlike the cart-centric
 * blocks it builds the availability context from the requested product (loaded by the route `id`
 * param) and the logged in customer's default shipping country, then delegates the render decision
 * to {@see AvailabilityEvaluator}. Virtual and downloadable products never render the button; the
 * per-selection virtual guard for bundle/grouped products is enforced server-side at temp-cart
 * build time.
 */
class ProductPage extends Template
{
    use WidgetTrait;

    /**
     * Product types never eligible for Express Checkout (SeQura requires a shippable order).
     */
    private const PRODUCT_TYPE_VIRTUAL = 'virtual';
    private const PRODUCT_TYPE_DOWNLOADABLE = 'downloadable';

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
    /**
     * @var Http
     */
    private Http $http;
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;
    /**
     * @var AvailabilityEvaluator
     */
    private AvailabilityEvaluator $availabilityEvaluator;
    /**
     * Memoized render state for the current request.
     *
     * @var string|null
     */
    private ?string $state = null;
    /**
     * Memoized product for the current request (null also means "resolved to nothing").
     *
     * @var Product|null
     */
    private ?Product $currentProduct = null;
    /**
     * Whether the current product has already been resolved this request.
     *
     * @var bool
     */
    private bool $productResolved = false;

    /**
     * @param ScopeResolverInterface $scopeResolver
     * @param ResolverInterface $localeResolver
     * @param Context $context
     * @param Request $request
     * @param CustomerSession $customerSession
     * @param Http $http
     * @param ProductRepositoryInterface $productRepository
     * @param AvailabilityEvaluator $availabilityEvaluator
     */
    public function __construct(
        ScopeResolverInterface $scopeResolver,
        ResolverInterface $localeResolver,
        Context $context,
        Request $request,
        CustomerSession $customerSession,
        Http $http,
        ProductRepositoryInterface $productRepository,
        AvailabilityEvaluator $availabilityEvaluator
    ) {
        parent::__construct($context);

        $this->scopeResolver = $scopeResolver;
        $this->localeResolver = $localeResolver;
        $this->request = $request;
        $this->customerSession = $customerSession;
        $this->http = $http;
        $this->productRepository = $productRepository;
        $this->availabilityEvaluator = $availabilityEvaluator;
    }

    /**
     * Whether the Express Checkout button should render for the viewed product.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->resolveState() === AvailabilityEvaluator::STATE_BUTTON;
    }

    /**
     * Whether the inline "not available" message should render in place of the button
     * (logged in customer whose default shipping country is not supported).
     *
     * @return bool
     */
    public function showUnavailableMessage(): bool
    {
        return $this->resolveState() === AvailabilityEvaluator::STATE_MESSAGE;
    }

    /**
     * The inline message shown when Express Checkout is unavailable for the logged in customer.
     *
     * @return string
     */
    public function getUnavailableMessage(): string
    {
        return (string)__('SeQura is not available for your account.');
    }

    /**
     * The viewed product's entity ID, for the JS controller.
     *
     * @return string
     */
    public function getProductId(): string
    {
        $product = $this->getCurrentProduct();

        return $product ? (string)$product->getId() : '';
    }

    /**
     * The viewed product's type ID (simple, configurable, bundle, grouped), for client-side gating.
     *
     * @return string
     */
    public function getProductType(): string
    {
        $product = $this->getCurrentProduct();

        return $product ? (string)$product->getTypeId() : '';
    }

    /**
     * The storefront REST URL the JS controller POSTs the add-to-cart form to.
     *
     * @return string
     *
     * @throws NoSuchEntityException
     */
    public function getProductSolicitUrl(): string
    {
        $store = $this->_storeManager->getStore();

        return $store->getBaseUrl() . 'rest/' . $store->getCode()
            . '/V1/sequra_core/express-checkout/product-solicit';
    }

    /**
     * Resolves — once per request — whether to render the button, the inline message or nothing.
     *
     * @return string One of AvailabilityEvaluator::STATE_*.
     */
    private function resolveState(): string
    {
        if ($this->state !== null) {
            return $this->state;
        }

        $this->state = AvailabilityEvaluator::STATE_HIDDEN;

        try {
            $product = $this->getCurrentProduct();
            if (!$product) {
                return $this->state;
            }

            $typeId = (string)$product->getTypeId();
            if (in_array($typeId, [self::PRODUCT_TYPE_VIRTUAL, self::PRODUCT_TYPE_DOWNLOADABLE], true)) {
                return $this->state;
            }

            $isLoggedIn = $this->customerSession->isLoggedIn();
            $country = $isLoggedIn ? $this->getCustomerDefaultShippingCountry() : '';

            $this->state = $this->availabilityEvaluator->evaluate(
                (string)$this->_storeManager->getStore()->getId(),
                ExpressCheckoutPage::product()->getPage(),
                $isLoggedIn,
                $country,
                $this->getCurrentCurrency(),
                $this->getCustomerIpAddress(),
                [(string)$product->getId()],
                $this->getProductCategoryIds($product)
            );

            return $this->state;
        } catch (Exception $e) {
            Logger::logError('Checking Express Checkout availability for product failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());

            return $this->state;
        }
    }

    /**
     * Returns the currently viewed product, resolved from the route `id` param.
     *
     * Memoized for the request; null means there is no valid product in context.
     *
     * @return Product|null
     */
    private function getCurrentProduct(): ?Product
    {
        if ($this->productResolved) {
            return $this->currentProduct;
        }

        $this->productResolved = true;

        $productId = $this->http->getParam('id');
        if (!is_numeric($productId)) {
            return null;
        }

        try {
            $storeId = (int)$this->_storeManager->getStore()->getId();
            /** @var Product $product */
            $product = $this->productRepository->getById((int)$productId, false, $storeId);
            $this->currentProduct = $product;
        } catch (Exception $e) {
            $this->currentProduct = null;
        }

        return $this->currentProduct;
    }

    /**
     * Returns the country of the logged in customer's default shipping address.
     *
     * Empty string when no default shipping address is set.
     *
     * @return string
     */
    private function getCustomerDefaultShippingCountry(): string
    {
        $address = $this->customerSession->getCustomer()->getDefaultShippingAddress();

        return $address ? (string)$address->getCountryId() : '';
    }

    /**
     * Collects the viewed product's category IDs for the per-category eligibility guard.
     *
     * @param Product $product
     *
     * @return string[]
     */
    private function getProductCategoryIds(Product $product): array
    {
        $categoryIds = [];
        foreach ($product->getCategoryIds() as $categoryId) {
            $categoryId = (string)$categoryId;
            if ($categoryId !== '') {
                $categoryIds[$categoryId] = $categoryId;
            }
        }

        return array_values($categoryIds);
    }
}
