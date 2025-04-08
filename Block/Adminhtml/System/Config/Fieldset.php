<?php

namespace Sequra\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset as MagentoFieldset;
use Magento\Framework\View\Helper\Js;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

class Fieldset extends MagentoFieldset
{
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;

    /**
     * @param SeQuraTranslationProvider $translationProvider
     * @param Context $context
     * @param Session $authSession
     * @param Js $jsHelper
     * @param mixed[] $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        SeQuraTranslationProvider $translationProvider,
        Context                   $context,
        Session                   $authSession,
        Js                        $jsHelper,
        array                     $data = [],
        ?SecureHtmlRenderer       $secureRenderer = null
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data, $secureRenderer);
        $this->translationProvider = $translationProvider;
    }

    /**
     * Get frontend class for fieldset
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getFrontendClass($element)
    {
        return parent::_getFrontendClass($element) . ' with-button enabled';
    }

    /**
     * Get header title html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return string
     */
    protected function _getHeaderTitleHtml($element)
    {
        $html = '<div class="config-heading" >';

        $html .= '<div class="button-container">' . $this->getConfigButtonElementHtml() . '</div>';
        if (method_exists($element, 'getLegend')) {
            $html .= '<div class="heading"><strong>' . $element->getLegend() . '</strong>';
        }

        if (method_exists($element, 'getComment') && $element->getComment()) {
            $html .= '<span class="heading-intro">' . $element->getComment() . '</span>';
        }
        $html .= '<div class="config-alt"></div>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Get config button element html
     *
     * @return string
     */
    public function getConfigButtonElementHtml()
    {
        /** @var Button $buttonBlock */
        $buttonBlock = $this->getLayout()->createBlock(Button::class);

        $params = ['store' => $buttonBlock->getRequest()->getParam('store')];

        $url = $this->getUrl("sequra/configuration/index", $params);
        $data = [
            'label' => $this->translationProvider->translate('sequra.configure'),
            'onclick' => "setLocation('" . $url . "')",
            'class' => '',
        ];

        return $buttonBlock->setData($data)->toHtml();
    }

    /**
     * Get collapsed state on-load
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     *
     * @return false
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _isCollapseState($element)
    {
        return false;
    }
}
