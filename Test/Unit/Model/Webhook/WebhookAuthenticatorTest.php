<?php

namespace Sequra\Core\Test\Unit\Model\Webhook;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\Domain\Integration\Store\StoreIdProvider;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use SeQura\Core\BusinessLogic\Webhook\Exceptions\InvalidSignatureException;
use SeQura\Core\BusinessLogic\Webhook\Validator\WebhookValidator;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Model\Webhook\WebhookAuthenticator;

class WebhookAuthenticatorTest extends TestCase
{
    private const PAYLOAD = ['order_ref' => 'a1b2c3', 'signature' => 'signed', 'sq_state' => 'cancelled'];

    /**
     * @var WebhookValidator&MockObject
     */
    private $validator;

    /**
     * Accepts a webhook the core validates, checking it against the store the webhook is for.
     *
     * @return void
     */
    public function testAcceptsAWebhookTheCoreValidatesInItsStore(): void
    {
        $validatedInStore = null;
        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (Webhook $webhook) use (&$validatedInStore): void {
                $validatedInStore = StoreContext::getInstance()->getStoreId();
                $this->assertSame('a1b2c3', $webhook->getOrderRef());
                $this->assertSame('signed', $webhook->getSignature());
            });

        $this->assertTrue((new WebhookAuthenticator())->isAuthentic('3', self::PAYLOAD));
        $this->assertSame('3', $validatedInStore);
    }

    /**
     * Rejects a webhook the core refuses, such as one with a wrong signature.
     *
     * @return void
     */
    public function testRejectsAWebhookTheCoreRefuses(): void
    {
        $this->validator->method('validate')
            ->willThrowException(new InvalidSignatureException('Signature mismatch.', 400));

        $this->assertFalse((new WebhookAuthenticator())->isAuthentic('3', self::PAYLOAD));
    }

    /**
     * Puts a stand-in for the core's webhook validator in reach.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->validator = $this->createMock(WebhookValidator::class);
        ServiceRegister::registerService(WebhookValidator::class, function (): WebhookValidator {
            return $this->validator;
        });
        ServiceRegister::registerService(StoreIdProvider::class, function (): StoreIdProvider {
            return $this->createMock(StoreIdProvider::class);
        });
    }
}
