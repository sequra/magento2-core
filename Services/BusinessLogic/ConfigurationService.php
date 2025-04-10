<?php

namespace Sequra\Core\Services\BusinessLogic;

use Magento\Framework\Exception\NoSuchEntityException;
use Sequra\Core\Helper\UrlHelper;
use SeQura\Core\Infrastructure\Configuration\Configuration;

class ConfigurationService extends Configuration
{
    public const MIN_LOG_LEVEL = 1;
    private const INTEGRATION_NAME = 'Magento2';

    /**
     * @var UrlHelper
     */
    private $urlHelper;

    /**
     * @param UrlHelper $urlHelper
     */
    public function __construct(UrlHelper $urlHelper)
    {
        parent::__construct();

        $this->urlHelper = $urlHelper;
    }

    /**
     * @inheritDoc
     */
    public function getIntegrationName(): string
    {
        return self::INTEGRATION_NAME;
    }

    /**
     * @inheritDoc
     *
     * @throws NoSuchEntityException
     */
    public function getAsyncProcessUrl($guid): string
    {
        $params = [
            'guid' => $guid,
            'ajax' => 1,
            '_nosid' => true,
        ];

        return $this->urlHelper->getFrontendUrl('sequra/asyncprocess/asyncprocess', $params);
    }
}
