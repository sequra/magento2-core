<?php

namespace Sequra\Core\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\CheckoutAPI\Banners\Requests\GetBannerForLocationRequest;
use SeQura\Core\BusinessLogic\CheckoutAPI\Banners\Responses\GetBannerForLocationResponse;
use SeQura\Core\BusinessLogic\CheckoutAPI\CheckoutAPI;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Services\BusinessLogic\BannerService;
use Sequra\Core\Services\BusinessLogic\CountryResolverService;
use Throwable;

/**
 * Class Banner
 *
 *  Implements required logic to show banner on storefront pages
 */
class Banner extends Template implements IdentityInterface
{
    public const CACHE_TAG = 'sequra_banner';

    /**
     * @var string[]
     */
    private const ALLOWED_DISPLAY_LOCATIONS = [
        BannerService::DISPLAY_ON_HOME_PAGE,
        BannerService::DISPLAY_ON_PRODUCT_PAGE,
        BannerService::DISPLAY_ON_CART_PAGE,
        BannerService::DISPLAY_ON_PRODUCT_LISTING_PAGE,
    ];

    /**
     * @var CountryResolverService
     */
    private CountryResolverService $countryResolver;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Context $context
     * @param CountryResolverService $countryResolver
     * @param StoreManagerInterface $storeManager
     * @param array $data
     *
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        CountryResolverService $countryResolver,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->countryResolver = $countryResolver;
        $this->storeManager = $storeManager;
    }

    /**
     * Returns banner data
     *
     * @return array<string, mixed>
     */
    public function getBannerData(): array
    {
        try {
            $rawLocation = $this->getData('display_location');
            $displayLocation = is_string($rawLocation) ? $rawLocation : '';
            if (!in_array($displayLocation, self::ALLOWED_DISPLAY_LOCATIONS, true)) {
                Logger::logError(
                    'Sequra banner block configured with unknown display_location: "' . $displayLocation
                );

                return [];
            }

            $country = $this->countryResolver->getCountry();
            if ($country === '') {
                return [];
            }

            $storeId = (string)$this->storeManager->getStore()->getId();

            /** @var GetBannerForLocationResponse $response */
            $response = CheckoutAPI::get()->banners($storeId)
                ->getBannerForLocation(new GetBannerForLocationRequest($country, $displayLocation));

            return $response->isSuccessful() ? $response->toArray() : [];
        } catch (Throwable $e) {
            Logger::logError(
                'Fetching seQura banner failed: ' . $e->getMessage()
                . ' Trace: ' . $e->getTraceAsString()
            );

            return [];
        }
    }

    /**
     * Tags the FPC response so the webhook can invalidate it per store.
     *
     * Picked up by Magento\PageCache\Model\Layout\LayoutPlugin::afterGetOutput(),
     * which writes the tags into the X-Magento-Tags header.
     *
     * @return array<int, string>
     *
     * @throws NoSuchEntityException
     */
    public function getIdentities(): array
    {
        $storeId = (string)$this->storeManager->getStore()->getId();

        return [self::CACHE_TAG, self::CACHE_TAG . '_' . $storeId];
    }
}
