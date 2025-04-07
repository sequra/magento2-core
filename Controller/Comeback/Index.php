<?php

namespace Sequra\Core\Controller\Comeback;

use Magento\Checkout\Controller\Onepage;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;

class Index extends Onepage
{
    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $manager;
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $orderFactory;
    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;
    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;
    /**
     * @var SeQuraTranslationProvider
     */
    private $translationProvider;

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
        \Magento\Framework\Message\ManagerInterface $manager,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        SeQuraTranslationProvider $translationProvider
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
        $this->manager = $manager;
        $this->orderFactory = $orderFactory;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->translationProvider = $translationProvider;
    }

    public function execute()
    {
        $quote = $this->quoteRepository->get(
            $this->getRequest()->getParam('cartId')
        );
        $order = $this->orderFactory->create()->loadByIncrementId($quote->getReservedOrderId());
        if (!$order->getId()) {
            $this->manager->addWarningMessage(
                $this->translationProvider->translate('sequra.error.somethingWentWrong')
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
        $sectiondata = json_decode($this->cookieManager->getCookie('section_data_ids') ?: '');
        if ($sectiondata) {
            $sectiondata->cart += 1000;
            $this->cookieManager->setPublicCookie(
                'section_data_ids',
                json_encode($sectiondata),
                $metadata
            );
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }
}
