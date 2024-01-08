<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Controller\Comeback;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;

/**
 * Comeback controller
 */
class Index extends \Magento\Checkout\Controller\Onepage
{
    /**
     * @var \Magento\Framework\Session\SessionManagerInterface
     */
    protected $cookieManager;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    protected $cookieMetadataFactory;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @param \Magento\Framework\App\Action\Context              $context
     * @param \Magento\Customer\Model\Session                    $customerSession
     * @param CustomerRepositoryInterface                        $customerRepository
     * @param AccountManagementInterface                         $accountManagement
     * @param \Magento\Framework\Registry                        $coreRegistry
     * @param \Magento\Framework\Translate\InlineInterface       $translateInline
     * @param \Magento\Framework\Data\Form\FormKey\Validator     $formKeyValidator
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\View\LayoutFactory              $layoutFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface         $quoteRepository
     * @param \Magento\Framework\View\Result\PageFactory         $resultPageFactory
     * @param \Magento\Framework\View\Result\LayoutFactory       $resultLayoutFactory
     * @param \Magento\Framework\Controller\Result\RawFactory    $resultRawFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory   $resultJsonFactory
     * @param \Magento\Sales\Model\OrderFactory                  $orderFactory
     * @param CookieManagerInterface                             $cookieManager
     * @param CookieMetadataFactory                              $cookieMetadataFactory
     *
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $accountManagement,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\Translate\InlineInterface $translateInline,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $customerRepository,
            $accountManagement,
            $coreRegistry,
            $translateInline,
            $formKeyValidator,
            $scopeConfig,
            $layoutFactory,
            $quoteRepository,
            $resultPageFactory,
            $resultLayoutFactory,
            $resultRawFactory,
            $resultJsonFactory
        );
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        $this->orderFactory = $orderFactory;
    }
    /**
     * Rebuild session and redirect to default success controller
     *
     * @return                                 void
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function execute()
    {
        $quote = $this->quoteRepository->get(
            $this->getRequest()->getParam('quote_id')
        );
        $order = $this->orderFactory->create()->loadByIncrementId($quote->getReservedOrderId());
        if (!$order->getId()) {
            $messageManager = $this->_objectManager->get(\Magento\Framework\Message\ManagerInterface::class);
            $messageManager->addWarningMessage(
                __('Lo sentimos. No se ha podido procesar el pago con SeQura, por favor, inténtelo de nuevo o utilice otro método de pago')
            );
            $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
        $session = $this->getOnepage()->getCheckout();
        // prepare session to success or cancellation page
        $session->clearHelperData();
        $session->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($quote->getReservedOrderId());
        // Reset minicart.
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setPath('/');
        $sectiondata = json_decode($this->cookieManager->getCookie('section_data_ids'));
        if($sectiondata){
            $sectiondata['cart'] += 1000;
            $this->cookieManager->setPublicCookie(
                'section_data_ids',
                json_encode($sectiondata),
                $metadata
            );
        }
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }
}
