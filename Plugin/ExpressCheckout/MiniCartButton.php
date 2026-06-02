<?php

namespace Sequra\Core\Plugin\ExpressCheckout;

use Magento\Checkout\CustomerData\Cart;
use Magento\Framework\View\LayoutInterface;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Block\ExpressCheckout\MiniCart;
use Throwable;

/**
 * Class MiniCartButton
 *
 * Appends the SeQura Express Checkout button to the mini-cart `extra_actions`
 * customer-data slot (rendered directly under "Proceed to Checkout"). The MiniCart block
 * renders nothing unless Express Checkout is available for the current logged in customer
 * and cart, so the button only appears when applicable.
 */
class MiniCartButton
{
    /**
     * @var LayoutInterface
     */
    private LayoutInterface $layout;

    /**
     * @param LayoutInterface $layout
     */
    public function __construct(LayoutInterface $layout)
    {
        $this->layout = $layout;
    }

    /**
     * Adds the Express Checkout button HTML to the mini-cart section data.
     *
     * @param Cart $subject
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        try {
            /** @var MiniCart $block */
            $block = $this->layout->createBlock(MiniCart::class);
            $html = $block->setTemplate('Sequra_Core::express/minicart.phtml')->toHtml();

            if ($html !== '') {
                $existing = isset($result['extra_actions']) ? (string)$result['extra_actions'] : '';
                $result['extra_actions'] = $existing . $html;
            }
        } catch (Throwable $e) {
            Logger::logError('Rendering Express Checkout mini-cart button failed: ' . $e->getMessage() .
                ' Trace: ' . $e->getTraceAsString());
        }

        return $result;
    }
}
