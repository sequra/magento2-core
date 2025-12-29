<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Integration\StoreIntegration\StoreIntegrationServiceInterface;
use SeQura\Core\BusinessLogic\Domain\StoreIntegration\Models\Capability;
use SeQura\Core\BusinessLogic\Domain\URL\Exceptions\InvalidUrlException;
use SeQura\Core\BusinessLogic\Domain\URL\Model\URL;
use Sequra\Core\Helper\UrlHelper;

/**
 * Class StoreIntegrationService
 *
 * @package Sequra\Core\Services\BusinessLogic
 */
class StoreIntegrationService implements StoreIntegrationServiceInterface
{
    /**
     * @var UrlHelper
     */
    private $urlHelper;

    public function __construct(urlHelper $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return URL
     *
     * @throws NoSuchEntityException
     * @throws InvalidUrlException
     */
    public function getWebhookUrl(): URL
    {
       return new URL($this->urlHelper->getFrontendUrl('sequra/integrationwebhook/index'));
    }

    /**
     * @return Capability[]
     */
    public function getSupportedCapabilities(): array
    {
       return [
           Capability::storeInfo(),
           Capability::general(),
           Capability::widget()
       ];
    }
}