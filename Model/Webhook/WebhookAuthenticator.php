<?php

namespace Sequra\Core\Model\Webhook;

use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Webhook\Models\Webhook;
use SeQura\Core\BusinessLogic\Webhook\Validator\WebhookValidator;
use SeQura\Core\Infrastructure\ServiceRegister;
use Throwable;

class WebhookAuthenticator
{
    /**
     * Tells whether the webhook names a SeQura order of the store and carries that order's signature.
     *
     * @param string $storeId
     * @param array $payload
     * @phpstan-param array<string, mixed> $payload
     *
     * @return bool
     */
    public function isAuthentic(string $storeId, array $payload): bool
    {
        try {
            StoreContext::doWithStore($storeId, function () use ($payload): void {
                /** @var WebhookValidator $validator */
                $validator = ServiceRegister::getService(WebhookValidator::class);
                $validator->validate(Webhook::fromArray($payload));
            });
        } catch (Throwable $exception) {
            return false;
        }

        return true;
    }
}
