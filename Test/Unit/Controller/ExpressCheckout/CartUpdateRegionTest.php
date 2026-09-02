<?php

namespace Sequra\Core\Test\Unit\Controller\ExpressCheckout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Controller\ExpressCheckout\CartUpdate;
use Sequra\Core\Model\Api\ExpressCheckout\BaseSolicitService;
use Sequra\Core\Model\ExpressCheckout\SolicitRateLimiter;

/**
 * Class CartUpdateRegionTest
 *
 * What this endpoint can and cannot say about the region the shopper picked.
 *
 * It can insist on the shape: the id is the host's own `directory_country_region.region_id`,
 * handed to the form in the cartDataReady payload and sent back untouched, so anything that is
 * not a plain integer was never offered and is refused before it reaches a database comparison.
 *
 * It cannot check which country the id belongs to, because it does not know the country: the
 * express address sheet has no country field — the country is shown read-only and comes from the
 * solicited quote — so the change never carries one, and a client-asserted country would prove
 * nothing anyway. That check lives in {@see \Sequra\Core\Model\ExpressCheckout\QuoteShippingResolver}
 * where the quote's own country is at hand, and a region that does not belong to it is not
 * written at all.
 */
class CartUpdateRegionTest extends TestCase
{
    /**
     * A complete address, minus the region — and minus the country, exactly as the express
     * address sheet sends it.
     */
    private const ADDRESS = [
        'givenName' => 'Marina',
        'surnames' => 'Garcia',
        'addressLine1' => 'Carrer de Pallars 128',
        'postalCode' => '08018',
        'city' => 'Barcelona',
        'mobilePhone' => '600123456',
    ];

    /**
     * @var MockObject&BaseSolicitService
     */
    private $solicitService;

    /**
     * @var MockObject&Json
     */
    private $result;

    /**
     * @var int|null
     */
    private $responseCode;

    /**
     * The picked region is carried into the change the solicit service applies, with no country
     * alongside it — the case every save from the express sheet actually produces.
     *
     * @return void
     */
    public function testAcceptsARegionSentWithoutACountry(): void
    {
        $controller = $this->controller('155');

        $this->solicitService->expects($this->once())
            ->method('update')
            ->with($this->callback(function (array $change): bool {
                return isset($change['address']['regionId']) && $change['address']['regionId'] === '155';
            }))
            ->willReturn(['type' => 'cartDataReady']);

        $controller->execute();

        $this->assertNull($this->responseCode);
    }

    /**
     * Anything that is not a plain integer is refused, and nothing is applied.
     *
     * @return void
     */
    public function testRejectsARegionThatIsNotAnId(): void
    {
        $controller = $this->controller('Barcelona');

        $this->solicitService->expects($this->never())->method('update');

        $controller->execute();

        $this->assertSame(400, $this->responseCode);
    }

    /**
     * Builds the controller over a posted region id.
     *
     * @param string $regionId Region id as the form posted it.
     *
     * @return CartUpdate
     */
    private function controller(string $regionId): CartUpdate
    {
        $payload = json_encode(['address' => self::ADDRESS + ['regionId' => $regionId]]);

        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturn($payload);

        $this->responseCode = null;
        $this->result = $this->createMock(Json::class);
        $this->result->method('setHeader')->willReturnSelf();
        $this->result->method('setData')->willReturnSelf();
        $this->result->method('setHttpResponseCode')->willReturnCallback(function (int $code): Json {
            $this->responseCode = $code;

            return $this->result;
        });

        $resultFactory = $this->createMock(JsonFactory::class);
        $resultFactory->method('create')->willReturn($this->result);

        $rateLimiter = $this->createMock(SolicitRateLimiter::class);
        $rateLimiter->method('isExceeded')->willReturn(false);

        $this->solicitService = $this->createMock(BaseSolicitService::class);

        return new CartUpdate(
            $request,
            $resultFactory,
            $this->createMock(CustomerSession::class),
            $this->solicitService,
            $rateLimiter
        );
    }
}
