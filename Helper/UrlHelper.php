<?php

namespace Sequra\Core\Helper;

use Magento\Backend\Model\UrlInterface as MagentoBackendUrl;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Url as MagentoUrl;

/**
 * Class UrlHelper
 *
 * @package Sequra\Core\Helper
 */
class UrlHelper
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var MagentoUrl
     */
    private $urlHelper;
    /**
     * @var MagentoBackendUrl
     */
    private $backendUrlHelper;

    /**
     * UrlHelper constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param MagentoUrl $urlHelper
     * @param MagentoBackendUrl $backendUrlHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        MagentoUrl            $urlHelper,
        MagentoBackendUrl     $backendUrlHelper
    )
    {
        $this->storeManager = $storeManager;
        $this->urlHelper = $urlHelper;
        $this->backendUrlHelper = $backendUrlHelper;
    }

    /**
     * Returns front-end controller URL.
     *
     * @param string $routePath Path.
     * @param array|null $routeParams Parameters.
     *
     * @return string Publicly visible URL of the requested front-end controller.
     *
     * @throws NoSuchEntityException
     */
    public function getFrontendUrl(string $routePath, array $routeParams = null): string
    {
        $storeView = $this->storeManager->getStore();
        $url = $this->urlHelper->setScope($storeView)->getUrl($routePath, $routeParams);

        if ($routeParams !== null) {
            return $url;
        }

        return explode('?', $url)[0];
    }

    /**
     * Returns back-end controller URL.
     *
     * @param string $routePath Path.
     * @param array|null $routeParams Parameters.
     *
     * @return string Publicly visible URL of the requested back-end controller.
     */
    public function getBackendUrl(string $routePath, array $routeParams = null): string
    {
        return $this->backendUrlHelper->getUrl($routePath, $routeParams);
    }
}
