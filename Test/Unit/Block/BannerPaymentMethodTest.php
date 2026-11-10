<?php

namespace Sequra\Core\Test\Unit\Block;

use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Sequra\Core\Block\Banner;
use Sequra\Core\Model\Config\PaymentMethodStatus;
use Sequra\Core\Services\BusinessLogic\CountryResolverService;

/**
 * Class BannerPaymentMethodTest
 *
 * That the banner is not built while the payment method is inactive.
 */
class BannerPaymentMethodTest extends TestCase
{
    /**
     * With the payment method inactive, the banner has no data and the country is not resolved.
     *
     * @return void
     */
    public function testHasNoDataWhenThePaymentMethodIsInactive(): void
    {
        $countryResolver = $this->createMock(CountryResolverService::class);
        $countryResolver->expects($this->never())->method('getCountry');

        $status = $this->createStub(PaymentMethodStatus::class);
        $status->method('isActive')->willReturn(false);

        $banner = new Banner(
            $this->createStub(Context::class),
            $countryResolver,
            $this->createStub(StoreManagerInterface::class),
            $status,
            ['display_location' => 'product']
        );

        $this->assertSame([], $banner->getBannerData());
    }
}
