<?php

namespace Sequra\Core\Test\Unit\Services\BusinessLogic\ExpressCheckout;

use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\DataAccess\ExpressCheckout\Entities\ExpressCheckoutSettings as CoreEntity;
use SeQura\Core\BusinessLogic\Domain\Checkout\Services\CheckoutService;
use SeQura\Core\BusinessLogic\Domain\CountryConfiguration\Services\CountryConfigurationService;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPage;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutSettings as CoreSettings;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Services\ExpressCheckoutService;
use SeQura\Core\BusinessLogic\Domain\Integration\ExpressCheckout\ExpressCheckoutIntegrationInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\BusinessLogic\Domain\PaymentMethod\Services\PaymentMethodsService;
use SeQura\Core\Infrastructure\ORM\Entity;
use SeQura\Core\Infrastructure\ORM\Interfaces\RepositoryInterface;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Entities\ExpressCheckoutSettings as SettingsEntity;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Exceptions\InvalidTermsAndConditionsUrlException;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Handlers\GetExpressCheckoutSettingsHandler;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Handlers\SaveExpressCheckoutSettingsHandler;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models\ExpressCheckoutSettings;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Repositories\ExpressCheckoutSettingsRepository;

class ExpressCheckoutSettingsHandlersTest extends TestCase
{
    private const URL = 'https://shop.example.com/terms';

    /**
     * @var Entity|null
     */
    private ?Entity $row = null;

    /**
     * @var int
     */
    private int $writes = 0;

    /**
     * @return void
     */
    public function testSavesAValidUrlWithTheCoreFields(): void
    {
        $this->save([
            'expressCheckoutConfigs' => [['page' => 'cart', 'enabled' => true]],
            'buttonStyle' => '{"a":1}',
            'termsAndConditionsUrl' => self::URL,
        ]);

        $stored = $this->storedSettings();
        $this->assertSame(self::URL, $stored['termsAndConditionsUrl']);
        $this->assertSame('{"a":1}', $stored['buttonStyle']);
        $this->assertSame([['page' => 'cart', 'enabled' => true]], $stored['expressCheckoutConfigs']);
    }

    /**
     * @return void
     */
    public function testKeepsTheStoredUrlWhenTheKeyIsMissing(): void
    {
        $this->givenStoredUrl(self::URL);

        $this->save(['expressCheckoutConfigs' => [['page' => 'product', 'enabled' => true]]]);

        $stored = $this->storedSettings();
        $this->assertSame(self::URL, $stored['termsAndConditionsUrl']);
        $this->assertSame([['page' => 'product', 'enabled' => true]], $stored['expressCheckoutConfigs']);
    }

    /**
     * @return void
     */
    public function testClearsTheStoredUrlOnNullOrEmpty(): void
    {
        foreach ([null, ''] as $empty) {
            $this->givenStoredUrl(self::URL);

            $this->save(['expressCheckoutConfigs' => [], 'termsAndConditionsUrl' => $empty]);

            $this->assertNull($this->storedSettings()['termsAndConditionsUrl']);
        }
    }

    /**
     * @return void
     */
    public function testRejectsAnInvalidUrlBeforeSavingAnything(): void
    {
        $this->givenStoredUrl(self::URL);

        foreach (['javascript:alert(1)', 'ftp://shop.example.com/terms', '/terms', 42] as $invalid) {
            try {
                $this->save([
                    'expressCheckoutConfigs' => [['page' => 'cart', 'enabled' => true]],
                    'termsAndConditionsUrl' => $invalid,
                ]);
                $this->fail('Accepted ' . var_export($invalid, true));
            } catch (InvalidTermsAndConditionsUrlException $exception) {
                $this->assertSame(0, $this->writes);
            }
        }

        $this->assertSame(self::URL, $this->storedSettings()['termsAndConditionsUrl']);
        $this->assertSame([], $this->storedSettings()['expressCheckoutConfigs']);
    }

    /**
     * @return void
     */
    public function testReadIncludesTheStoredUrl(): void
    {
        $this->givenStoredUrl(self::URL);

        $response = $this->get();

        $this->assertSame(self::URL, $response['termsAndConditionsUrl']);
        $this->assertSame(['product', 'cart'], $response['availablePages']);
        $this->assertArrayHasKey('expressCheckoutConfigs', $response);
        $this->assertArrayHasKey('buttonStyle', $response);
    }

    /**
     * @return void
     */
    public function testReadReturnsANullUrlWhenNoneIsStored(): void
    {
        $this->assertArrayHasKey('termsAndConditionsUrl', $this->get());
        $this->assertNull($this->get()['termsAndConditionsUrl']);

        $legacy = new CoreEntity();
        $legacy->setStoreId('1');
        $legacy->setExpressCheckoutSettings(new CoreSettings());
        $this->row = $legacy;

        $this->assertNull($this->get()['termsAndConditionsUrl']);
    }

    /**
     * @param string $url
     *
     * @return void
     */
    private function givenStoredUrl(string $url): void
    {
        $entity = new SettingsEntity();
        $entity->setId(1);
        $entity->setStoreId('1');
        $entity->setExpressCheckoutSettings(new ExpressCheckoutSettings([], null, $url));
        $this->row = $entity;
        $this->writes = 0;
    }

    /**
     * @param mixed[] $payload
     *
     * @return void
     */
    private function save(array $payload): void
    {
        (new SaveExpressCheckoutSettingsHandler($this->service()))->handle($payload);
    }

    /**
     * @return mixed[]
     */
    private function get(): array
    {
        $integration = $this->createStub(ExpressCheckoutIntegrationInterface::class);
        $integration->method('getAvailablePages')->willReturn(
            [ExpressCheckoutPage::product(), ExpressCheckoutPage::cart()]
        );

        return (new GetExpressCheckoutSettingsHandler($this->service(), $integration))->handle([])->toArray();
    }

    /**
     * @return mixed[]
     */
    private function storedSettings(): array
    {
        $this->assertNotNull($this->row);

        return $this->row->toArray()['expressCheckoutSettings'];
    }

    /**
     * @return ExpressCheckoutService
     */
    private function service(): ExpressCheckoutService
    {
        $orm = $this->createStub(RepositoryInterface::class);
        $orm->method('selectOne')->willReturnCallback(function () {
            return $this->row;
        });
        $store = function (Entity $entity) {
            $this->writes++;
            $this->row = $entity;

            return 1;
        };
        $orm->method('save')->willReturnCallback($store);
        $orm->method('update')->willReturnCallback($store);

        $storeContext = $this->createStub(StoreContext::class);
        $storeContext->method('getStoreId')->willReturn('1');

        return new ExpressCheckoutService(
            new ExpressCheckoutSettingsRepository($orm, $storeContext),
            $this->createStub(CheckoutService::class),
            $this->createStub(CountryConfigurationService::class),
            $this->createStub(PaymentMethodsService::class),
            $this->createStub(OrderService::class)
        );
    }
}
