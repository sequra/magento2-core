<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\Exception\NoSuchEntityException;
use SeQura\Core\BusinessLogic\Domain\Integration\StoreIntegration\StoreIntegrationServiceInterface;
use SeQura\Core\BusinessLogic\Domain\StoreIntegration\Models\Capability;
use SeQura\Core\BusinessLogic\Domain\URL\Exceptions\InvalidUrlException;
use SeQura\Core\BusinessLogic\Domain\URL\Model\URL;
use Sequra\Core\Helper\UrlHelper;

class StoreIntegrationService implements StoreIntegrationServiceInterface
{
    /**
     * @var UrlHelper
     */
    private $urlHelper;

    /**
     * @param UrlHelper $urlHelper
     */
    public function __construct(urlHelper $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * Returns webhook url for integration.
     *
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
     * Returns an array of supported capabilities.
     *
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
