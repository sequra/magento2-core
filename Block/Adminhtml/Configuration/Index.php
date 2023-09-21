<?php

namespace Sequra\Core\Block\Adminhtml\Configuration;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Asset\Repository;
use Sequra\Core\Helper\UrlHelper;

/**
 * Class Index
 *
 */
class Index extends Template
{
    /**
     * @var UrlHelper
     */
    private $urlHelper;

    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @var Repository
     */
    private $assetRepository;

    /**
     * Content constructor.
     *
     * @param Context $context
     * @param UrlHelper $urlHelper
     * @param Resolver $localeResolver
     * @param Repository $assetRepository
     * @param array $data
     */
    public function __construct(
        Context    $context,
        UrlHelper  $urlHelper,
        Resolver   $localeResolver,
        Repository $assetRepository,
        array      $data = []
    )
    {
        parent::__construct($context, $data);

        $this->urlHelper = $urlHelper;
        $this->localeResolver = $localeResolver;
        $this->assetRepository = $assetRepository;
    }

    /**
     * Returns URL to backend controller that provides data for the configuration page.
     *
     * @param string $controllerName Name of the configuration controller.
     * @param string $storeId Store id.
     * @param string $action Controller action.
     *
     * @return string URL to backend configuration controller.
     *
     * @throws LocalizedException
     */
    public function getControllerUrl(
        string $controllerName,
        string $storeId,
        string $action,
        string $identifier = null
    ): string
    {
        $routeParams = [
            'storeId' => $storeId,
            'action' => $action,
            'form_key' => $this->formKey->getFormKey()
        ];

        $identifier && $routeParams['identifier'] = $identifier;

        return $this->urlHelper->getBackendUrl('sequra/configuration/' . strtolower($controllerName), $routeParams);
    }

    /**
     * Returns Sequra module translations in the default and the current system language.
     *
     * @return array
     */
    public function getTranslations(): array
    {
        $currentLocale = substr($this->localeResolver->getLocale(), 0, 2);
        $default = json_decode(file_get_contents($this->assetRepository->getUrl('Sequra_Core::lang/en.json')), false);
        $current = [];

        if (file_exists($this->assetRepository->getUrl('Sequra_Core::lang/' . $currentLocale . '.json'))) {
            $current = json_decode(
                file_get_contents($this->assetRepository->getUrl('Sequra_Core::lang/' . $currentLocale . '.json')),
                false
            );
        }

        return [
            'default' => str_replace("'", "\\'", json_encode($default)),
            'current' => str_replace("'", "\\'", json_encode($current)),
        ];
    }
}
