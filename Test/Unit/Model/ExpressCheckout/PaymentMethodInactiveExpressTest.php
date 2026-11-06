<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Checkout\Model\Session;
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
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Model\Api\CartProvider\CartProvider;
use Sequra\Core\Model\Api\ExpressCheckout\BaseSolicitService;
use Sequra\Core\Model\Config\PaymentMethodStatus;
use Sequra\Core\Model\ExpressCheckout\AvailabilityEvaluator;
use Sequra\Core\Model\ExpressCheckout\CartSummaryFormDecorator;
use Sequra\Core\Model\ExpressCheckout\ExpressCheckoutFlow;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;
use Sequra\Core\Model\ExpressCheckout\SolicitedQuoteRegistry;

/**
 * Class PaymentMethodInactiveExpressTest
 *
 * That Express Checkout neither solicits nor loads the cart while the payment method is inactive.
 */
class PaymentMethodInactiveExpressTest extends TestCase
{
    /**
     * With the payment method inactive, a solicit is refused before the cart is read.
     *
     * @return void
     */
    public function testSolicitIsRefusedWhenThePaymentMethodIsInactive(): void
    {
        $cartProvider = $this->createMock(CartProvider::class);
        $cartProvider->expects($this->never())->method('getQuote');

        $this->expectException(WebapiException::class);

        $this->service($cartProvider, $this->createStub(CartRepositoryInterface::class))->solicit('42');
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

        $this->expectException(WebapiException::class);

        $this->service($this->createStub(CartProvider::class), $quoteRepository)->update('aToken', []);
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

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $context = $this->createStub(Context::class);
        $context->method('getStoreManager')->willReturn($storeManager);

        $block = new MiniCart(
            $this->createStub(ScopeResolverInterface::class),
            $this->createStub(ResolverInterface::class),
            $context,
            $session,
            $this->createStub(Request::class),
            $this->createStub(QuoteShippingResolver::class),
            new AvailabilityEvaluator($this->inactive())
        );

        $this->assertFalse($block->isAvailable());
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
            $this->inactive()
        );
    }

    /**
     * A payment method status that reads as inactive.
     *
     * @return PaymentMethodStatus
     */
    private function inactive(): PaymentMethodStatus
    {
        $status = $this->createStub(PaymentMethodStatus::class);
        $status->method('isActive')->willReturn(false);

        return $status;
    }
}
