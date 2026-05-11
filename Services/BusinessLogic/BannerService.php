<?php

namespace Sequra\Core\Services\BusinessLogic;

use SeQura\Core\BusinessLogic\Domain\Integration\Banner\BannerServiceInterface;

/**
 * Class BannerService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class BannerService implements BannerServiceInterface
{
    public const DISPLAY_ON_HOME_PAGE = 'displayOnHomePage';
    public const DISPLAY_ON_PRODUCT_PAGE = 'displayOnProductPage';
    public const DISPLAY_ON_CART_PAGE = 'displayOnCartPage';
    public const DISPLAY_ON_PRODUCT_LISTING_PAGE = 'displayOnProductListingPage';

    public function getBannerDisplayLocations(): array
    {
        return [
            self::DISPLAY_ON_HOME_PAGE,
            self::DISPLAY_ON_PRODUCT_PAGE,
            self::DISPLAY_ON_CART_PAGE,
            self::DISPLAY_ON_PRODUCT_LISTING_PAGE,
        ];
    }
}