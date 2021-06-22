<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Service;

use Sequra\Core\Api\SubmissionInterface;

/**
 * Class SubmissionService
 *
 */
class SubmissionService implements SubmissionInterface
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Framework\App\Action\Context
     */
    protected $context;

    /**
     * @var \Sequra\Core\Model\Api\BuilderFactory
     */
    protected $builderFactory;

    /**
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Sequra\Core\Model\Api\BuilderFactory $builderFactory,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
    ) {

        $this->quoteRepository = $quoteRepository;
        $this->cookieManager = $cookieManager;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->builderFactory = $builderFactory;
        $this->context = $context;
    }

    public function getForm()
    {
        $quote = $this->checkoutSession->getQuote();
        $quote->reserveOrderId();
        if($this->getConfigData('allow_remotesales')) {
            $quote->setSequraRemoteSale($this->isRemoteSale());
            $quote->setSequraOperatorRef(
                $this->cookieManager->getCookie('SEQURA_OPERATOR_REF')?:'-'
            );
        }
        $this->quoteRepository->save($quote);

        $data = $this->builderFactory->create('order')
            ->setQuoteAsOrder($quote)
            ->build()
            ->getData();
        $client = new \Sequra\PhpClient\Client(
            $this->getConfigData('user_name'),
            $this->getConfigData('user_secret'),
            $this->getConfigData('endpoint')
        );
        $client->startSolicitation($data);
        $url = $client->getOrderUri();
        if (!$client->succeeded()) {
            http_response_code($client->getStatus());
            die();
        }
        $payment_code = $quote->getPayment()->getMethod();
        $options = array(
            'ajax' => true,
            'product' => $this->getPaymentConfigData($payment_code, 'product'),
            'campaign' => $this->getPaymentConfigData($payment_code, 'campaign')
        );
        if($this->isRemoteSale()){
            $client->sendIdentificationForm($url, $options);
            //@ Todo move html out of here
            if($client->succeeded()){
                return '<div id="sequra-remotesales" style="display:none">
                    <div>
                        <h2>SMS ENVIADO</h2>
                    </div>
                    <div>
                        <p>
                        Hemos enviado SMS al cliente al número de teléfono del destinatario.<br/>
                        No modifique el carrito mientras el cliente realiza el pago.<br/>
                        El enlace enviado no tiene fecha de caducidad.<br/>
                        Cierra esta pestaña y revisa desde el admin si el cliente pudo hacer el pago correctamente. <br/>
                        </p>
                    </div>
                </div>
                ';
            }else{
                return '<div id="sequra-remotesales" style="display:none">
                    <div>
                        <h2>No se ha podido enviar el SMS</h2>
                    </div>
                    <div>
                        <p>Está limitado el número máximo de SMS que se pueden enviar cada cierto tiempo.<br/>
                        Por favor, espera y vuelve a intentarlo más tarde.</p>
                    </div>
                </div>
                ';
            }
        }
        return $client->getIdentificationForm($url, $options);
    }

    private function isRemoteSale(){
        return $this->getConfigData('allow_remotesales') &&
            !!$this->cookieManager->getCookie('SEQURA_OPERATOR_REF');
    }

    public function getConfigData($field, $storeId = null)
    {
        $path = 'sequra/core/' . $field;

        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getPaymentConfigData($payment_code, $field, $storeId = null)
    {
        $path = 'payment/' . $payment_code . '/' . $field;

        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
