<?php

namespace Sequra\Core\Model\Config\ContentType\AdditionalData\Provider;

use Exception;
use Magento\Framework\Data\Form\FormKey;
use Sequra\Core\Helper\UrlHelper;

/**
 * Class WidgetConfig
 *
 * @package Sequra\Core\Model\Config\ContentType\AdditionalData\Provider
 */
class WidgetConfig
{
    /**
     * @var UrlHelper
     */
    private $urlHelper;
    /**
     * @var FormKey
     */
    protected $formKey;

    /**
     * @param UrlHelper $urlHelper
     * @param FormKey $formKey
     */
    public function __construct(UrlHelper $urlHelper, FormKey $formKey)
    {
        $this->urlHelper = $urlHelper;
        $this->formKey = $formKey;
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
        $routeParams = [
            'action' => 'getData',
            'form_key' => $this->formKey->getFormKey()
        ];

        return [
            $itemName => [
                'sequraUrl' => $this->urlHelper->getBackendUrl(
                    'sequra/configuration/widgetsdataprovider',
                    $routeParams
                )
            ]
        ];
    }
}
