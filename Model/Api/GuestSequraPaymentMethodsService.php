<?php

namespace Sequra\Core\Model\Api;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Sequra\Core\Api\GuestSequraPaymentMethodsInterface;

/**
 * Class GuestSequraPaymentMethodsService
 *
 * @package Sequra\Core\Model\Api
 */
class GuestSequraPaymentMethodsService extends AbstractrSequraPaymentMethodsService implements GuestSequraPaymentMethodsInterface
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var Validator
     */
    protected $formKeyValidator;
    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * AbstractInternalApiController constructor.
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param GuestCartRepositoryInterface $quoteResotory
     * @param \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     */
    public function __construct(
        Http $request,
        Json $jsonSerializer,
        Validator $formKeyValidator,
        GuestCartRepositoryInterface $guestCartRepository,
        CartRepositoryInterface $cartRepository,
        \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
    ) {
        parent::__construct($request, $jsonSerializer, $formKeyValidator, $createOrderRequestBuilderFactory);

        $this->guestCartRepository = $guestCartRepository;
        $this->cartRepository = $cartRepository;
    }

    protected function getQuote(string $cartId): Quote
    {
        /** @var Quote $quote */
        $quote = $this->guestCartRepository->get($cartId);
        if (!$quote->getIsActive()) {
            throw NoSuchEntityException::singleField('cartId', $cartId);
        }

        if ($quote->getCheckoutMethod() !== Onepage::METHOD_GUEST) {
            $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            $quote->setCustomerIsGuest(true);
            $this->cartRepository->save($quote);
        }

        return $quote;
    }
}
