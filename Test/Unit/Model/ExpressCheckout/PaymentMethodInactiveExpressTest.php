<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Block\ExpressCheckout\MiniCart;
use Sequra\Core\Block\ExpressCheckout\ProductPage;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Model\Api\CartProvider\CartProvider;
use Sequra\Core\Model\Api\ExpressCheckout\BaseSolicitService;
use Sequra\Core\Model\Api\ExpressCheckout\ProductSolicitService;
use Sequra\Core\Model\Config\PaymentMethodStatus;
use Sequra\Core\Model\ExpressCheckout\AvailabilityEvaluator;
use Sequra\Core\Model\ExpressCheckout\CartSummaryFormDecorator;
use Sequra\Core\Model\ExpressCheckout\ExpressCheckoutFlow;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;
use Sequra\Core\Model\ExpressCheckout\SolicitedQuoteRegistry;
use Sequra\Core\Model\ExpressCheckout\TemporaryCartBuilder;

/**
 * Class PaymentMethodInactiveExpressTest
 *
 * That Express Checkout neither solicits nor loads the quote or the product while the payment method is inactive.
 */
class PaymentMethodInactiveExpressTest extends TestCase
{
    /**
     * With the payment method inactive, a solicit is refused before the quote is read.
     *
     * @return void
     */
    public function testSolicitIsRefusedWhenThePaymentMethodIsInactive(): void
    {
        $cartProvider = $this->createMock(CartProvider::class);
        $cartProvider->expects($this->never())->method('getQuote');

        $this->assertNotEligible(
            fn () => $this->service($cartProvider, $this->createStub(CartRepositoryInterface::class))->solicit('42')
        );
    }

    /**
     * With the payment method inactive, a product solicit is refused before the temporary cart is built.
     *
     * @return void
     */
    public function testProductSolicitIsRefusedBeforeBuildingTheTemporaryCart(): void
    {
        $builder = $this->createMock(TemporaryCartBuilder::class);
        $builder->expects($this->never())->method($this->anything());

        $service = new ProductSolicitService(
            $builder,
            $this->service($this->createStub(CartProvider::class), $this->createStub(CartRepositoryInterface::class))
        );

        $this->assertNotEligible(fn () => $service->solicit(['product' => '1', 'qty' => '1']));
    }

    /**
     * With the payment method inactive, a cart update is refused before the solicited quote is read.
     *
     * @return void
     */
    public function testUpdateIsRefusedWhenThePaymentMethodIsInactive(): void
    {
        $quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $quoteRepository->expects($this->never())->method('get');

        $this->assertNotEligible(
            fn () => $this->service($this->createStub(CartProvider::class), $quoteRepository)->update('aToken', [])
        );
    }

    /**
     * With the payment method inactive, the mini-cart button is hidden without loading the quote.
     *
     * @return void
     */
    public function testMiniCartIsHiddenWithoutLoadingTheQuote(): void
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->never())->method('getQuote');

        $block = new MiniCart(
            $this->createStub(ScopeResolverInterface::class),
            $this->createStub(ResolverInterface::class),
            $this->contextForStore(),
            $session,
            $this->createStub(Request::class),
            $this->createStub(QuoteShippingResolver::class),
            new AvailabilityEvaluator($this->inactivePaymentMethod())
        );

        $this->assertFalse($block->isAvailable());
    }

    /**
     * With the payment method inactive, the product page button is hidden without loading the product.
     *
     * @return void
     */
    public function testProductPageIsHiddenWithoutLoadingTheProduct(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->expects($this->never())->method($this->anything());
        $http = $this->createStub(Http::class);
        $http->method('getParam')->willReturn('5');

        $block = new ProductPage(
            $this->createStub(ScopeResolverInterface::class),
            $this->createStub(ResolverInterface::class),
            $this->contextForStore(),
            $this->createStub(Request::class),
            $http,
            $productRepository,
            new AvailabilityEvaluator($this->inactivePaymentMethod())
        );

        $this->assertFalse($block->isAvailable());
    }

    /**
     * Asserts that the call is refused as not eligible (HTTP 422).
     *
     * @param callable $call
     *
     * @return void
     */
    private function assertNotEligible(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected the request to be refused as not eligible.');
        } catch (WebapiException $e) {
            $this->assertSame(422, $e->getHttpCode());
        }
    }

    /**
     * A block context whose store manager returns store 1.
     *
     * @return Context
     */
    private function contextForStore(): Context
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $context = $this->createStub(Context::class);
        $context->method('getStoreManager')->willReturn($storeManager);

        return $context;
    }

    /**
     * A solicit service whose payment method is inactive.
     *
     * @param CartProvider $cartProvider
     * @param CartRepositoryInterface $quoteRepository
     *
     * @return BaseSolicitService
     */
    private function service(CartProvider $cartProvider, CartRepositoryInterface $quoteRepository): BaseSolicitService
    {
        return new BaseSolicitService(
            $cartProvider,
            $this->createStub(CreateOrderRequestBuilderFactory::class),
            $this->createStub(QuoteShippingResolver::class),
            $this->createStub(CartSummaryFormDecorator::class),
            $quoteRepository,
            $this->createStub(SolicitedQuoteRegistry::class),
            new ExpressCheckoutFlow(),
            $this->inactivePaymentMethod()
        );
    }

    /**
     * A payment method status that reads as inactive.
     *
     * @return PaymentMethodStatus
     */
    private function inactivePaymentMethod(): PaymentMethodStatus
    {
        $status = $this->createStub(PaymentMethodStatus::class);
        $status->method('isActive')->willReturn(false);

        return $status;
    }
}
