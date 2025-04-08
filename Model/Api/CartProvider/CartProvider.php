<?php

namespace Sequra\Core\Model\Api\CartProvider;

use Magento\Quote\Model\Quote;

// TODO: Interface should have name that ends with "Interface" suffix.
// phpcs:disable Magento2.NamingConvention.InterfaceName.WrongInterfaceName
/**
 * Interface CartProviderInterface
 *
 * Provides methods to retrieve the quote object from a cart ID
 */
interface CartProvider
{
    /**
     * Gets the quote object based on the cart ID
     *
     * @param string $cartId Cart identifier
     * @return Quote The quote object
     */
    public function getQuote(string $cartId): Quote;
}
