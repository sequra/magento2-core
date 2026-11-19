<?php

namespace Sequra\Core\Test\Unit\Services\BusinessLogic;

use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\Domain\StoreIntegration\Models\Capability;
use Sequra\Core\Helper\UrlHelper;
use Sequra\Core\Services\BusinessLogic\StoreIntegrationService;

class StoreIntegrationServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function testDeclaresExpressCheckoutRegardlessOfTheMerchantContract(): void
    {
        $service = new StoreIntegrationService($this->createMock(UrlHelper::class));

        $capabilities = array_map(static function (Capability $capability): string {
            return $capability->getCapability();
        }, $service->getSupportedCapabilities());

        $this->assertSame(
            [
                Capability::storeInfo()->getCapability(),
                Capability::general()->getCapability(),
                Capability::widget()->getCapability(),
                Capability::banner()->getCapability(),
                Capability::hostedCheckout()->getCapability(),
                Capability::listingSelectors()->getCapability(),
                Capability::expressCheckout()->getCapability(),
                Capability::advanced()->getCapability(),
            ],
            $capabilities
        );
    }
}
