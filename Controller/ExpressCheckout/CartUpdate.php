<?php

namespace Sequra\Core\Controller\ExpressCheckout;

use Exception;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Exception as WebapiException;
use SeQura\Core\Infrastructure\Logger\Logger;
use Sequra\Core\Model\Api\ExpressCheckout\BaseSolicitService;
use Sequra\Core\Model\ExpressCheckout\SolicitRateLimiter;

/**
 * Class CartUpdate
 *
 * Express Checkout cart-update endpoint: the other half of the CartSummary round trip. The
 * checkout-form posts a `Sequra.cartUpdate` message to the host page whenever the shopper saves
 * an address, picks a carrier or saves an email; the script CartSummaryFormDecorator injects
 * relays it here, and this controller applies the change to the solicited quote, re-solicits and
 * answers with the refreshed cartDataReady payload the form adopts without reloading its iframe.
 *
 * CSRF: this is a state-changing frontend POST, so it is left under Magento's default form-key
 * validation — no CsrfAwareActionInterface, no exemption. The injected script sends the session
 * form key minted server-side alongside the payload, which is what
 * Magento\Framework\Data\Form\FormKey\Validator checks.
 *
 * Everything in the body is shopper-supplied and treated as hostile: it is type-checked and
 * length-bounded here, the carrier reference is matched against the quote's actually-available
 * rates before it is applied, and the cart itself is never named by the client — it is the quote
 * the last solicit recorded on the customer session.
 */
class CartUpdate implements HttpPostActionInterface
{
    /**
     * Address fields accepted from the form, mapped to their maximum accepted length. Anything
     * else in the posted address object is ignored; anything longer is rejected outright rather
     * than silently truncated into the quote.
     */
    private const ADDRESS_FIELD_LIMITS = [
        'reference' => 64,
        'fullName' => 255,
        'givenName' => 128,
        'surnames' => 128,
        'addressLine1' => 255,
        'addressLine2' => 255,
        'postalCode' => 32,
        'city' => 128,
        'countryCode' => 2,
    ];

    /**
     * Address fields that must be present and non-empty when an address is sent at all.
     */
    private const REQUIRED_ADDRESS_FIELDS = ['givenName', 'surnames', 'addressLine1', 'postalCode', 'city'];

    /**
     * Maximum accepted length of a shipping method reference.
     */
    private const MAX_REFERENCE_LENGTH = 128;

    /**
     * Maximum accepted length of an email address.
     */
    private const MAX_EMAIL_LENGTH = 255;

    /**
     * @var HttpRequest
     */
    private HttpRequest $request;
    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;
    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;
    /**
     * @var BaseSolicitService
     */
    private BaseSolicitService $solicitService;
    /**
     * @var SolicitRateLimiter
     */
    private SolicitRateLimiter $rateLimiter;

    /**
     * CartUpdate constructor.
     *
     * @param HttpRequest $request
     * @param JsonFactory $resultJsonFactory
     * @param CustomerSession $customerSession
     * @param BaseSolicitService $solicitService
     * @param SolicitRateLimiter $rateLimiter
     */
    public function __construct(
        HttpRequest $request,
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        BaseSolicitService $solicitService,
        SolicitRateLimiter $rateLimiter
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->solicitService = $solicitService;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Applies the shopper's change to the solicited cart and answers with the refreshed payload.
     *
     * The response is per-session and each call re-solicits, so it must never be page-cached.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private', true)
            ->setHeader('Pragma', 'no-cache', true);

        try {
            $customerId = (int)$this->customerSession->getCustomerId();
            if ($customerId <= 0) {
                // 401 matches the solicit endpoints: the storefront opens the login pop-up.
                return $result->setHttpResponseCode(WebapiException::HTTP_UNAUTHORIZED)->setData([]);
            }

            // Throttle per customer on the same counter as the solicits: every update re-solicits
            // and therefore creates SeQura order state.
            if ($this->rateLimiter->isExceeded((string)$customerId)) {
                return $result->setHttpResponseCode(429)->setData([]);
            }

            return $result->setData($this->solicitService->update($this->parseChange()));
        } catch (WebapiException $e) {
            // 400 malformed change / unavailable carrier, 422 not eligible.
            return $result->setHttpResponseCode($e->getHttpCode())->setData([]);
        } catch (NoSuchEntityException $e) {
            // No solicit has run in this session, or its quote is gone (the order was placed in
            // another tab) — there is nothing to update, so a 400 rather than an opaque 500.
            return $result->setHttpResponseCode(WebapiException::HTTP_BAD_REQUEST)->setData([]);
        } catch (Exception $e) {
            Logger::logError('Express Checkout cart update failed: ' . $e->getMessage());

            return $result->setHttpResponseCode(500)->setData([]);
        }
    }

    /**
     * Reads and validates the shopper's change from the request body.
     *
     * All three parts are optional and any combination may arrive, but a malformed one rejects
     * the whole request: half-applying a change would re-solicit an order against figures the
     * shopper never saw.
     *
     * @return array<string, mixed> Validated change, with at least one of address,
     *                              shippingMethodReference and email.
     *
     * @throws WebapiException HTTP 400 when the body is not a well-formed change.
     */
    private function parseChange(): array
    {
        $raw = $this->request->getParam('payload');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw $this->badRequest();
        }

        $change = [];

        if (isset($decoded['address'])) {
            $change['address'] = $this->parseAddress($decoded['address']);
        }

        if (isset($decoded['shippingMethodReference'])) {
            $reference = $this->parseString($decoded['shippingMethodReference'], self::MAX_REFERENCE_LENGTH);
            if ($reference === '') {
                throw $this->badRequest();
            }
            $change['shippingMethodReference'] = $reference;
        }

        if (isset($decoded['email'])) {
            $email = $this->parseString($decoded['email'], self::MAX_EMAIL_LENGTH);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw $this->badRequest();
            }
            $change['email'] = $email;
        }

        if ($change === []) {
            throw $this->badRequest();
        }

        return $change;
    }

    /**
     * Validates the posted address object into the trimmed, bounded fields the resolver applies.
     *
     * @param mixed $value Posted address object.
     *
     * @return array<string, string>
     *
     * @throws WebapiException HTTP 400 when the address is not a well-formed address.
     */
    private function parseAddress($value): array
    {
        if (!is_array($value)) {
            throw $this->badRequest();
        }

        $address = [];
        foreach (self::ADDRESS_FIELD_LIMITS as $field => $limit) {
            $parsed = isset($value[$field]) ? $this->parseString($value[$field], $limit) : '';
            if ($parsed === '') {
                if (in_array($field, self::REQUIRED_ADDRESS_FIELDS, true)) {
                    throw $this->badRequest();
                }

                continue;
            }

            $address[$field] = $parsed;
        }

        if (isset($address['countryCode'])) {
            if (!preg_match('/^[A-Za-z]{2}$/', $address['countryCode'])) {
                throw $this->badRequest();
            }

            $address['countryCode'] = strtoupper($address['countryCode']);
        }

        return $address;
    }

    /**
     * Accepts a scalar-free, length-bounded string field and returns it trimmed.
     *
     * @param mixed $value Posted field value.
     * @param int $maxLength Maximum accepted length, in characters.
     *
     * @return string
     *
     * @throws WebapiException HTTP 400 when the value is not a string, or is over the limit.
     */
    private function parseString($value, int $maxLength): string
    {
        if (!is_string($value) || mb_strlen($value) > $maxLength) {
            throw $this->badRequest();
        }

        return trim($value);
    }

    /**
     * Builds the HTTP 400 exception for a malformed change.
     *
     * @return WebapiException
     */
    private function badRequest(): WebapiException
    {
        return new WebapiException(
            __('Invalid Express Checkout request.'),
            0,
            WebapiException::HTTP_BAD_REQUEST
        );
    }
}
