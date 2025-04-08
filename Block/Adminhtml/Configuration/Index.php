<?php

namespace Sequra\Core\Block\Adminhtml\Configuration;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Exception\LocalizedException;
use Sequra\Core\Helper\UrlHelper;

class Index extends Template
{
    /**
     * @var UrlHelper
     */
    private $urlHelper;
    /**
     * @var Session
     */
    private $authSession;

    /**
     * Content constructor.
     *
     * @param Context $context
     * @param UrlHelper $urlHelper
     * @param Session $authSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        UrlHelper $urlHelper,
        Session $authSession,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->urlHelper = $urlHelper;
        $this->authSession = $authSession;
    }

    /**
     * Returns URL to backend controller that provides data for the configuration page.
     *
     * @param string $controllerName Name of the configuration controller.
     * @param string $storeId Store id.
     * @param string $action Controller action.
     * @param string|null $identifier Optional identifier parameter.
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
    ): string {
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
        $currentLocale = substr($this->authSession->getUser()->getInterfaceLocale(), 0, 2);
        // TODO: The use of function file_get_contents() is discouraged
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $default = json_decode(file_get_contents(__DIR__ . '/../../../view/adminhtml/web/lang/en.json'), false);
        $current = [];

        // TODO: The use of function file_exists() is discouraged
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        if (file_exists(__DIR__ . '/../../../view/adminhtml/web/lang/' . $currentLocale . '.json')) {
            $current = json_decode(
                // TODO: The use of function file_get_contents() is discouraged
                // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
                file_get_contents(__DIR__ . '/../../../view/adminhtml/web/lang/' . $currentLocale . '.json'),
                false
            );
        }

        return [
            'default' => str_replace("'", "\\'", json_encode($default)),
            'current' => str_replace("'", "\\'", json_encode($current)),
        ];
    }

    /**
     * Get the admin language
     *
     * @return string
     */
    public function getAdminLanguage(): string
    {
        return strtoupper(substr($this->authSession->getUser()->getInterfaceLocale(), 0, 2));
    }
}
