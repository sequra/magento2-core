<?php

namespace Sequra\Core\Model\Api;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Sequra\Core\Api\SequraPaymentMethodsInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey\Validator;

/**
 * Class SequraPaymentMethodsService
 *
 * @package Sequra\Core\Model\Api
 */
class SequraPaymentMethodsService extends AbstractrSequraPaymentMethodsService implements SequraPaymentMethodsInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteResotory;

    /**
     * AbstractInternalApiController constructor.
     * @param Http $request
     * @param Validator $formKeyValidator
     * @param CartRepositoryInterface $quoteResotory
     * @param \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
     */
    public function __construct(
        Http $request,
        Json $jsonSerializer,
        Validator $formKeyValidator,
        CartRepositoryInterface $quoteResotory,
        \Sequra\Core\Model\Api\Builders\CreateOrderRequestBuilderFactory $createOrderRequestBuilderFactory
    ) {
        parent::__construct($request, $jsonSerializer, $formKeyValidator, $createOrderRequestBuilderFactory);

        $this->quoteResotory = $quoteResotory;
    }

    protected function getQuote(string $cartId): Quote
    {
        /** @var Quote $quote */
        $quote = $this->quoteResotory->getActive($cartId);

        if ($quote->getCheckoutMethod()) {
            $quote->setCheckoutMethod('');
            $quote->setCustomerIsGuest(false);
            $this->quoteResotory->save($quote);
        }

        return $quote;
    }
}
