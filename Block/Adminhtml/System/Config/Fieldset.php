<?php

namespace Sequra\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Fieldset as MagentoFieldset;

class Fieldset extends MagentoFieldset
{
    protected function _getFrontendClass($element)
    {
        return parent::_getFrontendClass($element) . ' with-button enabled';
    }

    protected function _getHeaderTitleHtml($element)
    {
        $html = '<div class="config-heading" >';

        $html .= '<div class="button-container">' . $this->getConfigButtonElementHtml() . '</div>';
        $html .= '<div class="heading"><strong>' . $element->getLegend() . '</strong>';

        if ($element->getComment()) {
            $html .= '<span class="heading-intro">' . $element->getComment() . '</span>';
        }
        $html .= '<div class="config-alt"></div>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * @return string
     */
    public function getConfigButtonElementHtml()
    {
        /** @var Button $buttonBlock  */
        $buttonBlock = $this->getLayout()->createBlock(Button::class);

        $params = ['store' => $buttonBlock->getRequest()->getParam('store')];

        $url = $this->getUrl("sequra/configuration/index", $params);
        $data = [
            'label' => __('Configure'),
            'onclick' => "setLocation('".$url."')",
            'class' => '',
        ];

        return $buttonBlock->setData($data)->toHtml();
    }

    /**
     * Get collapsed state on-load
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return false
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _isCollapseState($element)
    {
        return false;
    }
}
