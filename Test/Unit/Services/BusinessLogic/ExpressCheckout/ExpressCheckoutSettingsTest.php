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
        $valid = [
            'https://shop.example.com/terms',
            'http://shop.example.com/terms?lang=es#top',
            'HTTPS://shop.example.com/terms',
            'https://tienda.es/términos-y-condiciones',
            'https://españa-shop.es/terms',
            'https://shop.example.com:8443/terms',
            'https://shop.example.com:65535/terms',
            'https://shop.example.com:/terms',
            'https://[2001:db8::1]/terms',
            'https://[2001:DB8::1]:8443/terms',
            'https://[::1]/terms',
            'https://[::ffff:192.0.2.1]/terms',
            'https://192.168.1.10/terms',
            'https://tienda-ñ.es/términos',
            'https://user:pass@shop.example.com/terms',
        ];

        foreach ($valid as $url) {
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
            'https:///terms',
            'https://:443/terms',
            'https://user@/terms',
            'javascript://shop.example.com/%0Aalert(1)',
            '//shop.example.com/terms',
            'https://shop.example.com/my terms',
            "https://shop.example.com/te\nrms",
            "https://shop.example.com/\0terms",
            'https://%/terms',
            'https://shop_example.com/terms',
            'https://shop.example.com:65536/terms',
            'https://shop.example.com:123456/terms',
            'https://a..b/x',
            'https://-a.com/terms',
            'https://a-.com/terms',
            'https://shop.example.com./terms',
            'https://[shop.example.com]/terms',
            'https://[a]/terms',
            'https://[1:2:3]/terms',
            'https://[::g]/terms',
            'https://[1::2::3]/terms',
            'https://[2001:db8::1%25eth0]/terms',
            "https://shop.example.com/my\u{00A0}terms",
            "https://shop\u{00A0}example.com/terms",
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
    public function testStoresTheUrlTrimmed(): void
    {
        $settings = new ExpressCheckoutSettings([], null, "  https://shop.example.com/terms \t");

        $this->assertSame('https://shop.example.com/terms', $settings->toArray()['termsAndConditionsUrl']);
    }

    /**
     * @return void
     */
    public function testTreatsAnEmptyUrlAsUnset(): void
    {
        $this->assertNull((new ExpressCheckoutSettings([], null, "  \t"))->toArray()['termsAndConditionsUrl']);
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
