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

    /**
     * Constructor for Index controller
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountManagementInterface $accountManagement
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Framework\Translate\InlineInterface $translateInline
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\View\LayoutFactory $layoutFactory
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory
     * @param \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\Message\ManagerInterface $manager
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SeQuraTranslationProvider $translationProvider
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

    /**
     * Execute action based on request and return result
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /**
         * @var int $cartId
         */
        $cartId = $this->getRequest()->getParam('cartId');
        $quote = $this->quoteRepository->get($cartId);
        $order = $this->orderFactory->create()->loadByIncrementId((string) $quote->getReservedOrderId());
        if (!$order->getId()) {
            $this->manager->addWarningMessage(
                $this->translationProvider->translate('sequra.error.somethingWentWrong')
            );
            $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $session = $this->getOnepage()->getCheckout();
        // prepare session to success or cancellation page
        $session->clearHelperData();
        // TODO: Call to an undefined method Magento\Checkout\Model\Session::setLastQuoteId()
        // @phpstan-ignore-next-line
        $session->setLastQuoteId($quote->getId())
            ->setLastSuccessQuoteId($quote->getId())
            ->setLastOrderId($order->getId())
            ->setLastRealOrderId($quote->getReservedOrderId());
        // Reset minicart.
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setPath('/');
        $sectiondata = json_decode($this->cookieManager->getCookie('section_data_ids') ?: '');
        if (is_object($sectiondata) && isset($sectiondata->cart)) {
            $sectiondata->cart += 1000;
            $this->cookieManager->setPublicCookie(
                'section_data_ids',
                (string) json_encode($sectiondata),
                $metadata
            );
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
    }
}
