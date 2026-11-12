<?php

namespace Sequra\Core\Test\Unit\Services\BusinessLogic\ExpressCheckout;

use Magento\Framework\App\ObjectManager;
use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Handlers\ExpressCheckout\GetExpressCheckoutSettingsHandler;
use SeQura\Core\BusinessLogic\ConfigurationWebhookAPI\Handlers\ExpressCheckout\SaveExpressCheckoutSettingsHandler;
use SeQura\Core\BusinessLogic\DataAccess\ExpressCheckout\Repositories\ExpressCheckoutSettingsRepository;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\RepositoryContracts\ExpressCheckoutSettingsRepositoryInterface;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\Bootstrap;

class ExpressCheckoutSettingsWiringTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        /** @var Bootstrap $bootstrap */
        $bootstrap = ObjectManager::getInstance()->create(Bootstrap::class);
        $bootstrap->initInstance();
    }

    /**
     * @return void
     */
    public function testServesTheSettingsWebhooksWithTheCoreHandlers(): void
    {
        $this->assertSame(
            SaveExpressCheckoutSettingsHandler::class,
            get_class(ServiceRegister::getService(SaveExpressCheckoutSettingsHandler::class))
        );
        $this->assertSame(
            GetExpressCheckoutSettingsHandler::class,
            get_class(ServiceRegister::getService(GetExpressCheckoutSettingsHandler::class))
        );
    }

    /**
     * @return void
     */
    public function testStoresTheSettingsThroughTheCoreRepository(): void
    {
        $this->assertSame(
            ExpressCheckoutSettingsRepository::class,
            get_class(ServiceRegister::getService(ExpressCheckoutSettingsRepositoryInterface::class))
        );
    }
}
