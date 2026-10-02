<?php

namespace Sequra\Core\Model\ExpressCheckout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Math\Random;

/**
 * Class SolicitedQuoteRegistry
 *
 * Which quote a given Express Checkout form is allowed to change.
 *
 * A single "last solicited quote" slot cannot answer that. Every solicit overwrote it, so a
 * shopper with a product form and a cart form open at once — or two product tabs — had the older
 * form's updates land on the newer form's quote: mutated, re-solicited, and answered with a cart
 * the shopper was not looking at. {@see TemporaryCartBuilder} already keeps its drafts apart per
 * surface for the same reason, but per-surface keys still collide between two tabs of one surface.
 *
 * So each solicit mints its own token and the form carries it back. The token is the only thing
 * that names a quote here: it is random rather than derived, so it cannot be computed from a quote
 * id, and the map lives on the session, so a token lifted from one shopper resolves to nothing in
 * anyone else's. The quote id itself never reaches the client.
 *
 * The map is bounded. Tokens are only retired by falling out of the newest {@see MAX_FLOWS}, which
 * is well past what a shopper can have open and keeps a session from growing without limit.
 */
class SolicitedQuoteRegistry
{
    /**
     * Session key holding the token => quote id map.
     */
    private const SESSION_KEY = 'sequra_express_solicited_quotes';

    /**
     * Token length. Alphanumeric over 62 characters, so guessing one is not a strategy.
     */
    private const TOKEN_LENGTH = 32;

    /**
     * How many express forms one session may hold open at once. The oldest is dropped beyond this.
     */
    private const MAX_FLOWS = 8;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var Random
     */
    private Random $random;

    /**
     * SolicitedQuoteRegistry constructor.
     *
     * @param CustomerSession $customerSession
     * @param Random $random
     */
    public function __construct(CustomerSession $customerSession, Random $random)
    {
        $this->customerSession = $customerSession;
        $this->random = $random;
    }

    /**
     * Registers a freshly solicited quote and returns the token naming it.
     *
     * @param int $quoteId Quote the solicit ran against.
     *
     * @return string Token for the form to send back with its updates.
     *
     * @throws \Magento\Framework\Exception\LocalizedException If no random source is available.
     */
    public function remember(int $quoteId): string
    {
        $token = $this->random->getRandomString(self::TOKEN_LENGTH);

        $flows = $this->flows();
        $flows[$token] = $quoteId;
        if (count($flows) > self::MAX_FLOWS) {
            $flows = array_slice($flows, -self::MAX_FLOWS, null, true);
        }

        // @phpstan-ignore-next-line magic method forwarded to Storage via SessionManager::__call
        $this->customerSession->setData(self::SESSION_KEY, $flows);

        return $token;
    }

    /**
     * The quote the given token names, or 0 when it names none in this session.
     *
     * A token that is absent, expired out of the map, or minted for someone else is all one
     * answer: nothing. The caller turns that into the same "no solicited cart" the endpoint
     * already reports, so a wrong token can never be told apart from a missing one.
     *
     * @param string $token Token the form sent back.
     *
     * @return int Quote id, or 0.
     */
    public function resolve(string $token): int
    {
        $flows = $this->flows();
        if (!isset($flows[$token]) || !is_scalar($flows[$token])) {
            return 0;
        }

        return (int)$flows[$token];
    }

    /**
     * The session's token => quote id map.
     *
     * @return array<string, mixed>
     */
    private function flows(): array
    {
        $stored = $this->customerSession->getData(self::SESSION_KEY);

        return is_array($stored) ? $stored : [];
    }
}
