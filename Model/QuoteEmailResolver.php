<?php

namespace Sequra\Core\Model;

use Magento\Quote\Model\Quote;

/**
 * Class QuoteEmailResolver
 *
 * The one place that decides which of a quote's several email fields is "the shopper's email".
 *
 * Shared so the create-order request and the Express Checkout CartSummary payload cannot drift:
 * the CartSummary page has to show the address the SeQura order was actually solicited with.
 */
class QuoteEmailResolver
{
    /**
     * The shopper's email, most authoritative first.
     *
     * Quote-level wins: an email the shopper saves on the Express Checkout CartSummary page is
     * applied to the quote, and the (unchanged) account email would otherwise always beat it. It
     * is also the email Magento places the order with (QuoteManagement::submitQuote).
     *
     * @param Quote $quote
     *
     * @return string Empty when the quote carries no email at all.
     */
    public function resolve(Quote $quote): string
    {
        return (string)($quote->getCustomerEmail()
            ?: $quote->getCustomer()->getEmail()
            ?: $quote->getBillingAddress()->getEmail()
            ?: $quote->getShippingAddress()->getEmail());
    }
}
