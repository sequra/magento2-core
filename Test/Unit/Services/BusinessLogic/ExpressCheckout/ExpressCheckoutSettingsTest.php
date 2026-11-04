<?php

namespace Sequra\Core\Test\Unit\Services\BusinessLogic\ExpressCheckout;

use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\DataAccess\ExpressCheckout\Entities\ExpressCheckoutSettings as CoreEntity;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPage;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutPageConfig;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Entities\ExpressCheckoutSettings as Entity;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Exceptions\InvalidTermsAndConditionsUrlException;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models\ExpressCheckoutSettings;

class ExpressCheckoutSettingsTest extends TestCase
{
    /**
     * @return void
     */
    public function testKeepsAnAbsoluteHttpUrl(): void
    {
        foreach (['https://shop.example.com/terms', 'http://shop.example.com/terms?lang=es#top'] as $url) {
            $settings = new ExpressCheckoutSettings([], null, $url);

            $this->assertSame($url, $settings->toArray()['termsAndConditionsUrl']);
        }
    }

    /**
     * @return void
     */
    public function testRejectsAnythingButAnAbsoluteHttpUrl(): void
    {
        $invalid = [
            'javascript:alert(1)',
            'data:text/html,<b>x</b>',
            'ftp://shop.example.com/terms',
            '/terms',
            'https://',
            42,
            ['https://shop.example.com/terms'],
        ];

        foreach ($invalid as $url) {
            try {
                new ExpressCheckoutSettings([], null, $url);
                $this->fail('Accepted ' . var_export($url, true));
            } catch (InvalidTermsAndConditionsUrlException $exception) {
                $this->assertSame(400, $exception->getCode());
            }
        }
    }

    /**
     * @return void
     */
    public function testTreatsAnEmptyUrlAsUnset(): void
    {
        $this->assertNull((new ExpressCheckoutSettings([], null, ''))->toArray()['termsAndConditionsUrl']);
        $this->assertNull((new ExpressCheckoutSettings())->toArray()['termsAndConditionsUrl']);
    }

    /**
     * @return void
     */
    public function testUsesATranslatableBadRequestError(): void
    {
        $exception = new InvalidTermsAndConditionsUrlException();

        $this->assertSame(400, $exception->getCode());
        $this->assertSame(
            'general.errors.expressCheckout.invalidTermsAndConditionsUrl',
            $exception->getTranslatableLabel()->getCode()
        );
    }

    /**
     * @return void
     */
    public function testEntityRoundTripsTheUrlWithTheCoreFields(): void
    {
        $entity = new Entity();
        $entity->setStoreId('1');
        $entity->setExpressCheckoutSettings(new ExpressCheckoutSettings(
            [new ExpressCheckoutPageConfig(ExpressCheckoutPage::product(), true)],
            '{"color":"red"}',
            'https://shop.example.com/terms'
        ));

        $data = $entity->toArray();
        $restored = new Entity();
        $restored->inflate($data);
        $settings = $restored->getExpressCheckoutSettings();

        $this->assertSame(Entity::class, $data['class_name']);
        $this->assertInstanceOf(ExpressCheckoutSettings::class, $settings);
        $this->assertSame('https://shop.example.com/terms', $settings->toArray()['termsAndConditionsUrl']);
        $this->assertSame('{"color":"red"}', $settings->getButtonStyle());
        $this->assertTrue($settings->isPageEnabled('product'));
        $this->assertSame('1', $restored->getStoreId());
    }

    /**
     * @return void
     */
    public function testEntityInflatesALegacyCoreRowWithoutTheUrl(): void
    {
        $legacy = [
            'class_name' => CoreEntity::class,
            'id' => 7,
            'storeId' => '1',
            'expressCheckoutSettings' => [
                'expressCheckoutConfigs' => [['page' => 'cart', 'enabled' => true]],
                'buttonStyle' => null,
            ],
        ];

        $entity = new Entity();
        $entity->inflate($legacy);
        $settings = $entity->getExpressCheckoutSettings();

        $this->assertInstanceOf(ExpressCheckoutSettings::class, $settings);
        $this->assertNull($settings->toArray()['termsAndConditionsUrl']);
        $this->assertTrue($settings->isPageEnabled('cart'));
    }

    /**
     * @return void
     */
    public function testEntityDropsAStoredUrlThatNoLongerValidates(): void
    {
        $entity = new Entity();
        $entity->inflate([
            'storeId' => '1',
            'expressCheckoutSettings' => [
                'expressCheckoutConfigs' => [['page' => 'cart', 'enabled' => true]],
                'termsAndConditionsUrl' => 'javascript:alert(1)',
            ],
        ]);
        $settings = $entity->getExpressCheckoutSettings();

        $this->assertInstanceOf(ExpressCheckoutSettings::class, $settings);
        $this->assertNull($settings->toArray()['termsAndConditionsUrl']);
        $this->assertTrue($settings->isPageEnabled('cart'));
    }
}
