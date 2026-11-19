<?php

namespace Sequra\Core\Test\Unit\Controller\Webhook;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreIdProvider;
use SeQura\Core\BusinessLogic\WebhookAPI\Controller\WebhookController;
use SeQura\Core\BusinessLogic\WebhookAPI\Response\WebhookErrorResponse;
use SeQura\Core\BusinessLogic\WebhookAPI\Response\WebhookSuccessResponse;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Controller\Webhook\Index;
use Sequra\Core\Model\Webhook\OrderWebhookLock;
use Sequra\Core\Model\Webhook\WebhookAuthenticator;

class IndexTest extends TestCase
{
    private const PAYLOAD = ['event' => 'cancelled', 'order_ref' => 'a1b2c3', 'm_storeId' => '1'];

    /**
     * @var HttpResponse&MockObject
     */
    private $response;

    /**
     * @var OrderWebhookLock&MockObject
     */
    private $orderWebhookLock;

    /**
     * @var WebhookAuthenticator&MockObject
     */
    private $webhookAuthenticator;

    /**
     * @var WebhookController&MockObject
     */
    private $coreController;

    /**
     * Handles an authentic webhook inside the lock of the SeQura order it is about.
     *
     * @return void
     */
    public function testHandlesAnAuthenticWebhookUnderTheLockOfItsOrder(): void
    {
        $this->webhookAuthenticator->method('isAuthentic')->with('1', $this->anything())->willReturn(true);
        $this->orderWebhookLock->expects($this->once())
            ->method('runExclusively')
            ->with('a1b2c3', $this->callback('is_callable'))
            ->willReturnCallback(function (string $orderReference, callable $process): bool {
                $process();

                return true;
            });
        $this->coreController->expects($this->once())
            ->method('handleRequest')
            ->willReturn(new WebhookSuccessResponse());
        $this->response->expects($this->never())->method('setHttpResponseCode');

        $this->controller(self::PAYLOAD)->execute();
    }

    /**
     * Answers 503 when another webhook for the order keeps the lock, so SeQura retries it later.
     *
     * @return void
     */
    public function testAsksSeQuraToRetryWhenAnotherWebhookForTheOrderIsStillRunning(): void
    {
        $this->webhookAuthenticator->method('isAuthentic')->willReturn(true);
        $this->orderWebhookLock->method('runExclusively')->willReturn(false);
        $this->coreController->expects($this->never())->method('handleRequest');
        $this->response->expects($this->once())->method('setHttpResponseCode')->with(503);
        $this->response->expects($this->once())->method('sendResponse');

        $this->controller(self::PAYLOAD)->execute();
    }

    /**
     * Leaves a webhook that fails authentication to the core's own answer, without taking a lock for it.
     *
     * @return void
     */
    public function testAnswersAWebhookThatFailsAuthenticationWithoutLockingForIt(): void
    {
        $this->webhookAuthenticator->method('isAuthentic')->willReturn(false);
        $this->orderWebhookLock->expects($this->never())->method('runExclusively');
        $this->coreController->expects($this->once())
            ->method('handleRequest')
            ->willReturn(new WebhookErrorResponse(new Exception('Signature mismatch.', 400)));
        $this->response->expects($this->once())->method('setHttpResponseCode')->with(410);

        $this->controller(self::PAYLOAD)->execute();
    }

    /**
     * Handles a webhook that names no order without authenticating or locking it.
     *
     * @return void
     */
    public function testHandlesAWebhookWithoutAnOrderReferenceWithoutLocking(): void
    {
        $this->webhookAuthenticator->expects($this->never())->method('isAuthentic');
        $this->orderWebhookLock->expects($this->never())->method('runExclusively');
        $this->coreController->expects($this->once())
            ->method('handleRequest')
            ->willReturn(new WebhookErrorResponse(new Exception('Order not found.', 404)));
        $this->response->expects($this->once())->method('setHttpResponseCode')->with(410);

        $this->controller(['event' => 'cancelled', 'm_storeId' => '1'])->execute();
    }

    /**
     * Still answers 409 when the core reports a conflict.
     *
     * @return void
     */
    public function testKeepsAnsweringAConflictWith409(): void
    {
        $this->webhookAuthenticator->method('isAuthentic')->willReturn(true);
        $this->orderWebhookLock->method('runExclusively')->willReturnCallback(
            function (string $orderReference, callable $process): bool {
                $process();

                return true;
            }
        );
        $this->coreController->method('handleRequest')
            ->willReturn(new WebhookErrorResponse(new Exception('Order already cancelled.', 409)));
        $this->response->expects($this->once())->method('setHttpResponseCode')->with(409);

        $this->controller(self::PAYLOAD)->execute();
    }

    /**
     * Prepares the collaborators each test configures and puts the core's webhook controller in reach.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->response = $this->createMock(HttpResponse::class);
        $this->orderWebhookLock = $this->createMock(OrderWebhookLock::class);
        $this->webhookAuthenticator = $this->createMock(WebhookAuthenticator::class);
        $this->coreController = $this->createMock(WebhookController::class);
        ServiceRegister::registerService(WebhookController::class, function (): WebhookController {
            return $this->coreController;
        });
        ServiceRegister::registerService(StoreIdProvider::class, function (): StoreIdProvider {
            return $this->createMock(StoreIdProvider::class);
        });
    }

    /**
     * Builds the controller over a POST request with the given SeQura payload.
     *
     * @param array $payload
     * @phpstan-param array<string, string> $payload
     *
     * @return Index
     */
    private function controller(array $payload): Index
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('isPost')->willReturn(true);
        $request->method('getPostValue')->willReturn($payload);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResponse')->willReturn($this->response);

        return new Index(
            $context,
            $this->createMock(InvoiceService::class),
            $this->createMock(InvoiceRepository::class),
            $this->createMock(TransactionFactory::class),
            $this->createMock(SearchCriteriaBuilder::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->orderWebhookLock,
            $this->webhookAuthenticator
        );
    }
}
