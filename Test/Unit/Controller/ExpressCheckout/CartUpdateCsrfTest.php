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
 * Class CartUpdateCsrfTest
 *
 * That this endpoint stays under Magento's form-key validation, and how it fails when the key is
 * not good.
 *
 * The module used to disable form-key validation for every route named `sequra` through a plugin
 * on {@see \Magento\Framework\App\Request\CsrfValidator}, which left this state-changing POST with
 * no CSRF protection at all. The plugin is gone; the controllers that genuinely cannot carry a
 * form key — the webhooks, the async runner and the hosted-payment-page return — now say so
 * individually, and this one does not.
 *
 * What cannot be asserted here is the enforcement itself: that lives in Magento's CsrfValidator,
 * which a unit test does not run. What is asserted is the controller's half of the contract — it
 * claims no exemption, and it answers a refusal in the shape the injected script can read.
 */
class CartUpdateCsrfTest extends TestCase
{
    /**
     * @var MockObject&Json
     */
    private $result;

    /**
     * @var int|null
     */
    private $responseCode;

    /**
     * @var array<string, string>
     */
    private $headers = [];

    /**
     * The endpoint claims no exemption: returning null leaves the verdict to Magento's default
     * form-key check, which is the whole point of the change.
     *
     * @return void
     */
    public function testDefersToTheDefaultFormKeyValidation(): void
    {
        $this->assertNull($this->controller()->validateForCsrf($this->createMock(HttpRequest::class)));
    }

    /**
     * A refused request is answered with a 403, not with Magento's 302 to the referer.
     *
     * The injected script reads the reply with fetch(), which follows redirects: a 302 arrives as
     * an ok response carrying the referer's HTML, so the script would treat a stale form key as a
     * success and then fail parsing it, silently. A 403 fails the response.ok check it already has.
     *
     * @return void
     */
    public function testRefusesWithAJsonForbiddenRatherThanARedirect(): void
    {
        $exception = $this->controller()->createCsrfValidationException($this->createMock(HttpRequest::class));

        $this->assertNotNull($exception);
        $this->assertSame($this->result, $exception->getReplaceResult());
        $this->assertSame(403, $this->responseCode);
    }

    /**
     * The refusal is per-session like every other reply from this endpoint, so it must not be
     * page-cached either.
     *
     * @return void
     */
    public function testTheRefusalIsNotCacheable(): void
    {
        $this->controller()->createCsrfValidationException($this->createMock(HttpRequest::class));

        $this->assertArrayHasKey('Cache-Control', $this->headers);
        $this->assertStringContainsString('no-store', $this->headers['Cache-Control']);
    }

    /**
     * Builds the controller over a JSON result that records what was set on it.
     *
     * @return CartUpdate
     */
    private function controller(): CartUpdate
    {
        $this->responseCode = null;
        $this->headers = [];

        $this->result = $this->createMock(Json::class);
        $this->result->method('setData')->willReturnSelf();
        $this->result->method('setHeader')->willReturnCallback(
            function (string $name, string $value): Json {
                $this->headers[$name] = $value;

                return $this->result;
            }
        );
        $this->result->method('setHttpResponseCode')->willReturnCallback(function (int $code): Json {
            $this->responseCode = $code;

            return $this->result;
        });

        $resultFactory = $this->createMock(JsonFactory::class);
        $resultFactory->method('create')->willReturn($this->result);

        return new CartUpdate(
            $this->createMock(HttpRequest::class),
            $resultFactory,
            $this->createMock(CustomerSession::class),
            $this->createMock(BaseSolicitService::class),
            $this->createMock(SolicitRateLimiter::class)
        );
    }
}
