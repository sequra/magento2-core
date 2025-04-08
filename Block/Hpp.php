<?php

namespace Sequra\Core\Block;

use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Framework\View\Element\Template;

class Hpp extends Template
{
    /**
     * @var DefaultConfigProvider
     */
    private $defaultConfigProvider;

    /**
     * Constructor for Hpp block
     *
     * @param Template\Context $context
     * @param DefaultConfigProvider $defaultConfigProvider
     */
    public function __construct(
        Template\Context $context,
        DefaultConfigProvider $defaultConfigProvider
    ) {
        parent::__construct($context);
        $this->defaultConfigProvider = $defaultConfigProvider;
    }

    /**
     * Get the configuration for the HPP block
     *
     * @return string|false
     */
    public function getConfig()
    {
        return json_encode($this->defaultConfigProvider->getConfig());
    }
}
