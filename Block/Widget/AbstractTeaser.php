<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Widget\Block\BlockInterface;

class AbstractTeaser extends Template implements BlockInterface
{

    static protected $_paymentCode;
    protected $_template = "widget/teaser.phtml";
    /**
     * @var \Sequra\Core\Model\Config
     */
    protected $_config;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        ConfigInterface $config,
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        $this->_config = $config;
        parent::__construct($context, $data);
    }

    public function getMaxOrderTotal()
    {
        return $this->_config->getMaxOrderTotal();
    }

    public function getJsUrl()
    {
        return $this->_assetRepo
            ->createAsset('Sequra_Core::js/sequrapayment.js')
            ->getUrl();
    }

    public function getCssUrl()
    {
        return $this->_assetRepo
            ->createAsset('Sequra_Core::css/sequrapayment.css')
            ->getUrl();
    }

    public function getCostUrl()
    {
        return $this->_config->getCostUrl(
            $this->_config->getProduct()
        );
    }
}