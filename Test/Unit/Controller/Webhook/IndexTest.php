<?php

namespace Sequra\Core\Test\Unit\Controller\Webhook;

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
use Sequra\Core\Controller\Webhook\Index;
use Sequra\Core\Model\Webhook\OrderWebhookLock;

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
     * Holds the webhook back under the lock of the SeQura order it is about.
     *
     * @return void
     */
    public function testHandlesTheWebhookUnderTheLockOfItsOrder(): void
    {
        $this->orderWebhookLock->expects($this->once())
            ->method('runExclusively')
            ->with('a1b2c3', $this->callback('is_callable'))
            ->willReturn(true);
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
        $this->orderWebhookLock->method('runExclusively')->willReturn(false);
        $this->response->expects($this->once())->method('setHttpResponseCode')->with(503);
        $this->response->expects($this->once())->method('sendResponse');

        $this->controller(self::PAYLOAD)->execute();
    }

    /**
     * Prepares the collaborators each test configures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->response = $this->createMock(HttpResponse::class);
        $this->orderWebhookLock = $this->createMock(OrderWebhookLock::class);
    }

    /**
     * Builds the controller over a POST request with the given SeQura payload.
     *
     * @param array<string, string> $payload
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
            $this->orderWebhookLock
        );
    }
}
