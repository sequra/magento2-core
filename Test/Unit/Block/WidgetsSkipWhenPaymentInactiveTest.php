<?php

namespace Sequra\Core\Test\Unit\Block;

use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\ProductRepository;
use Magento\Checkout\Block\Cart as CartBlock;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Block\Cart;
use Sequra\Core\Block\Product;
use Sequra\Core\Block\WidgetInitializer;
use Sequra\Core\Model\Config\PaymentMethodStatus;

/**
 * Class WidgetsSkipWhenPaymentInactiveTest
 *
 * That the widget blocks return no data, and read neither the store nor the quote,
 * while the payment method is inactive.
 */
class WidgetsSkipWhenPaymentInactiveTest extends TestCase
{
    /**
     * @var MockObject&StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var MockObject&Session
     */
    private $session;

    /**
     * @var Stub&PaymentMethodStatus
     */
    private $inactive;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->expects($this->never())->method('getStore');
        $this->session = $this->createMock(Session::class);
        $this->session->expects($this->never())->method('getQuote');
        $this->inactive = $this->createStub(PaymentMethodStatus::class);
        $this->inactive->method('isActive')->willReturn(false);
    }

    /**
     * With the payment method inactive, the cart widget has nothing to show.
     *
     * @return void
     */
    public function testCartWidgetIsEmptyWhenThePaymentMethodIsInactive(): void
    {
        $block = new Cart(
            $this->createStub(ScopeResolverInterface::class),
            $this->createStub(ResolverInterface::class),
            $this->context(),
            $this->session,
            $this->createStub(Request::class),
            $this->inactive
        );

        $this->assertSame([], $block->getAvailableWidgets());
    }

    /**
     * With the payment method inactive, the product page widget has nothing to show.
     *
     * @return void
     */
    public function testProductWidgetIsEmptyWhenThePaymentMethodIsInactive(): void
    {
        $block = new Product(
            $this->createStub(ScopeResolverInterface::class),
            $this->createStub(ResolverInterface::class),
            $this->context(),
            $this->session,
            $this->createStub(Request::class),
            $this->createStub(Http::class),
            $this->inactive
        );

        $this->assertSame([], $block->getAvailableWidgets());
    }

    /**
     * With the payment method inactive, both initializer methods return no data.
     *
     * @return void
     */
    public function testWidgetInitializerIsEmptyWhenThePaymentMethodIsInactive(): void
    {
        $block = new WidgetInitializer(
            $this->context(),
            $this->createStub(ResolverInterface::class),
            $this->session,
            $this->createStub(Http::class),
            $this->createStub(ProductRepository::class),
            $this->createStub(CartBlock::class),
            $this->createStub(Data::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->storeManager,
            $this->inactive
        );

        $this->assertSame([], $block->getInitializationData());
        $this->assertSame([], $block->getWidgetInitializeData());
    }

    /**
     * With the payment method inactive, the Hyvä widget config is the same empty JSON as for unsupported pages.
     *
     * @return void
     */
    public function testWidgetConfigIsEmptyWhenThePaymentMethodIsInactive(): void
    {
        $request = $this->createMock(Http::class);
        $request->expects($this->never())->method('getFullActionName');

        $block = new WidgetInitializer(
            $this->context(),
            $this->createStub(ResolverInterface::class),
            $this->session,
            $request,
            $this->createStub(ProductRepository::class),
            $this->createStub(CartBlock::class),
            $this->createStub(Data::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->storeManager,
            $this->inactive
        );

        $this->assertSame('[]', $block->getWidgetConfig());
    }

    /**
     * A block context whose store manager must not be used.
     *
     * @return Stub&Context
     */
    private function context()
    {
        $context = $this->createStub(Context::class);
        $context->method('getStoreManager')->willReturn($this->storeManager);

        return $context;
    }
}
