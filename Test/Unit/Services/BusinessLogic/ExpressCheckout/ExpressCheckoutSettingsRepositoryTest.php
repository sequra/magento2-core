<?php

namespace Sequra\Core\Test\Unit\Services\BusinessLogic\ExpressCheckout;

use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\DataAccess\ExpressCheckout\Entities\ExpressCheckoutSettings as CoreEntity;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPage;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPageConfig;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutSettings as CoreSettings;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\Infrastructure\ORM\Entity;
use SeQura\Core\Infrastructure\ORM\Interfaces\RepositoryInterface;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Entities\ExpressCheckoutSettings as SettingsEntity;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models\ExpressCheckoutSettings;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Repositories\ExpressCheckoutSettingsRepository;

class ExpressCheckoutSettingsRepositoryTest extends TestCase
{
    private const URL = 'https://shop.example.com/terms';

    /**
     * @var Entity[]
     */
    private array $saved = [];

    /**
     * @var Entity[]
     */
    private array $updated = [];

    /**
     * @return void
     */
    public function testInsertsANewRowWithTheUrl(): void
    {
        $this->repository(null)->setExpressCheckoutSettings(new ExpressCheckoutSettings([], null, self::URL));

        $this->assertCount(0, $this->updated);
        $this->assertCount(1, $this->saved);
        $this->assertInstanceOf(SettingsEntity::class, $this->saved[0]);
        $this->assertSame('1', $this->saved[0]->toArray()['storeId']);
        $this->assertSame(self::URL, $this->storedUrl($this->saved[0]));
    }

    /**
     * @return void
     */
    public function testRewritesALegacyCoreRowSoItKeepsTheUrl(): void
    {
        $legacy = new CoreEntity();
        $legacy->setId(7);
        $legacy->setStoreId('1');
        $legacy->setExpressCheckoutSettings(new CoreSettings());

        $this->repository($legacy)->setExpressCheckoutSettings(new ExpressCheckoutSettings([], null, self::URL));

        $this->assertCount(0, $this->saved);
        $this->assertCount(1, $this->updated);
        $this->assertInstanceOf(SettingsEntity::class, $this->updated[0]);
        $this->assertSame(7, $this->updated[0]->getId());
        $this->assertSame(SettingsEntity::class, $this->updated[0]->toArray()['class_name']);
        $this->assertSame(self::URL, $this->storedUrl($this->updated[0]));
    }

    /**
     * @return void
     */
    public function testKeepsTheStoredUrlWhenSavingCoreSettings(): void
    {
        $existing = new SettingsEntity();
        $existing->setId(3);
        $existing->setStoreId('1');
        $existing->setExpressCheckoutSettings(new ExpressCheckoutSettings([], null, self::URL));
        $pages = [new ExpressCheckoutPageConfig(ExpressCheckoutPage::cart(), true)];

        $this->repository($existing)->setExpressCheckoutSettings(new CoreSettings($pages, '{"a":1}'));

        $this->assertCount(1, $this->updated);
        $settings = $this->updated[0]->toArray()['expressCheckoutSettings'];
        $this->assertSame(self::URL, $settings['termsAndConditionsUrl']);
        $this->assertSame('{"a":1}', $settings['buttonStyle']);
        $this->assertSame([['page' => 'cart', 'enabled' => true]], $settings['expressCheckoutConfigs']);
    }

    /**
     * @return void
     */
    public function testReadsALegacyCoreRow(): void
    {
        $legacy = new CoreEntity();
        $legacy->setStoreId('1');
        $legacy->setExpressCheckoutSettings(new CoreSettings());

        $this->assertInstanceOf(CoreSettings::class, $this->repository($legacy)->getExpressCheckoutSettings());
    }

    /**
     * @param Entity|null $existing
     *
     * @return ExpressCheckoutSettingsRepository
     */
    private function repository(?Entity $existing): ExpressCheckoutSettingsRepository
    {
        $orm = $this->createStub(RepositoryInterface::class);
        $orm->method('selectOne')->willReturn($existing);
        $orm->method('save')->willReturnCallback(function (Entity $entity) {
            $this->saved[] = $entity;

            return 1;
        });
        $orm->method('update')->willReturnCallback(function (Entity $entity) {
            $this->updated[] = $entity;

            return true;
        });

        $storeContext = $this->createStub(StoreContext::class);
        $storeContext->method('getStoreId')->willReturn('1');

        return new ExpressCheckoutSettingsRepository($orm, $storeContext);
    }

    /**
     * @param Entity $entity
     *
     * @return mixed
     */
    private function storedUrl(Entity $entity)
    {
        return $entity->toArray()['expressCheckoutSettings']['termsAndConditionsUrl'] ?? null;
    }
}
