<?php

namespace Sequra\Core\Model\Config\ContentType\AdditionalData\Provider;

use Exception;
use Magento\PageBuilder\Model\Config\ContentType\AdditionalData\ProviderInterface;
use Sequra\Core\Services\BusinessLogic\WidgetConfigService;

/**
 * Class WidgetConfig
 *
 * @package Sequra\Core\Model\Config\ContentType\AdditionalData\Provider
 */
class WidgetConfig implements ProviderInterface
{
    /**
     * @var WidgetConfigService
     */
    private $widgetConfigService;

    /**
     * @param WidgetConfigService $widgetConfigService
     */
    public function __construct(WidgetConfigService $widgetConfigService)
    {
        $this->widgetConfigService = $widgetConfigService;
    }

    /**
     * @param string $itemName
     *
     * @return array
     *
     * @throws Exception
     */
    public function getData(string $itemName): array
    {
        return [$itemName => $this->widgetConfigService->getData()];
    }
}
