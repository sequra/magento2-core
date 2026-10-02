<?php

namespace Sequra\Core\Test\Unit\Model\ExpressCheckout;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Model\Api\ExpressCheckout\BaseSolicitService;
use Sequra\Core\Model\Api\CartProvider\CartProvider;
use Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory;
use Sequra\Core\Model\ExpressCheckout\CartSummaryFormDecorator;
use Sequra\Core\Model\ExpressCheckout\ExpressCheckoutFlow;
use Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver;
use Sequra\Core\Model\ExpressCheckout\SolicitedQuoteRegistry;

/**
 * Class SolicitedQuoteGuardTest
 *
 * That a cart update cannot reach a quote whose order has already been placed.
 *
 * A flow token keeps naming its quote until it falls out of the registry, so once the shopper has
 * paid it still resolves to the quote that was converted. A duplicate, late or
 * replayed update would otherwise mutate that quote and re-solicit it — and core's
 * OrderService::solicitFor drops the stored SeQura order row and re-stores whatever comes back,
 * so the record of the order the shopper actually paid for would be replaced by a fresh unpaid
 * solicit, taking webhook, capture and refund sync with it.
 *
 * A reserved order id is the signal, the same one
 * {@see \Sequra\Core\Services\BusinessLogic\Order\OrderCreation} and
 * {@see \Sequra\Core\Model\ExpressCheckout\TemporaryCartBuilder} use. The is_active flag is not:
 * express drafts are deliberately left inactive between solicits so they never shadow the real
 * cart, so an inactive quote is the normal case here rather than a placed one.
 */
class SolicitedQuoteGuardTest extends TestCase
{
    /**
     * @var MockObject&QuoteShippingResolver
     */
    private $shippingResolver;

    /**
     * A placed quote is refused, and nothing is applied to it.
     *
     * @return void
     */
    public function testRefusesAQuoteWhoseOrderIsAlreadyPlaced(): void
    {
        $service = $this->service($this->quoteWith('000000123', false));

        $this->shippingResolver->expects($this->never())->method('applyChange');

        $this->expectException(NoSuchEntityException::class);

        $service->update('aToken', ['email' => 'marina@example.com']);
    }

    /**
     * An inactive draft with no reservation is the ordinary case between solicits, so it is
     * accepted — guarding on is_active would break every express update.
     *
     * @return void
     */
    public function testAcceptsTheInactiveDraftLeftBehindByTheLastSolicit(): void
    {
        $service = $this->service($this->quoteWith('', false));

        $this->shippingResolver->expects($this->once())->method('applyChange')->willReturn(false);

        // applyChange is stubbed to refuse, so the call stops at the "not eligible" 422 rather than
        // going on to solicit. Reaching applyChange at all is the point: the guard let it through.
        $this->expectExceptionMessage('SeQura Express Checkout is not available for this account.');

        $service->update('aToken', ['email' => 'marina@example.com']);
    }

    /**
     * Builds the service over a remembered quote.
     *
     * @param Quote $quote Quote the session points at.
     *
     * @return BaseSolicitService
     */
    private function service(Quote $quote): BaseSolicitService
    {
        $registry = $this->createMock(SolicitedQuoteRegistry::class);
        $registry->method('resolve')->willReturn(77);

        $quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $quoteRepository->method('get')->willReturn($quote);

        $this->shippingResolver = $this->createMock(QuoteShippingResolver::class);

        return new BaseSolicitService(
            $this->createMock(CartProvider::class),
            $this->createOrderRequestBuilderFactory(),
            $this->shippingResolver,
            $this->createMock(CartSummaryFormDecorator::class),
            $quoteRepository,
            $registry,
            new ExpressCheckoutFlow()
        );
    }

    /**
     * Mocks the create-order request builder factory.
     *
     * It is one of Magento's auto-generated factories, so it has no source file to autoload and
     * `createMock()` cannot find it. The guard under test throws long before the factory is
     * touched, so an unautoloaded double is enough.
     *
     * @return MockObject
     */
    private function createOrderRequestBuilderFactory(): MockObject
    {
        return $this->getMockBuilder(CreateOrderRequestBuilderFactory::class)
            ->disableOriginalConstructor()
            ->disableAutoload()
            ->getMock();
    }

    /**
     * A quote carrying the given reservation and active flag.
     *
     * @param string $reservedOrderId Reserved increment id, or '' when the quote is unplaced.
     * @param bool $isActive Whether the quote is still flagged active.
     *
     * @return Quote
     */
    private function quoteWith(string $reservedOrderId, bool $isActive): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quote->setData('reserved_order_id', $reservedOrderId);
        $quote->setData('is_active', $isActive);

        return $quote;
    }
}
