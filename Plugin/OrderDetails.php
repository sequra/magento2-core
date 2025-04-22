<?php

namespace Sequra\Core\Plugin;

use Magento\Directory\Model\Currency;
use Magento\Framework\UrlInterface;
use Magento\Sales\Block\Adminhtml\Order\View\Tab\Info;
use SeQura\Core\BusinessLogic\Domain\Order\Exceptions\OrderNotFoundException;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\OrderRequestStates;
use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\BusinessLogic\Domain\Order\Service\OrderService;
use SeQura\Core\Infrastructure\ServiceRegister;
use Sequra\Core\Services\BusinessLogic\Utility\SeQuraTranslationProvider;
use Sequra\Core\Helper\UrlHelper;

class OrderDetails
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var UrlHelper
     */
    protected $urlHelper;

    /**
     * @var Currency
     */
    protected $currencyModel;

    /**
     * @var SeQuraTranslationProvider
     */
    protected $translation;

    /**
     * @var OrderService|null
     */
    private $orderService;

    private const STATUS_MAP = [
        OrderRequestStates::CONFIRMED => 'sequra.status.paid',
        OrderRequestStates::ON_HOLD => 'sequra.status.pendingReview',
        OrderRequestStates::CANCELLED => 'sequra.status.cancelled',
    ];

    /**
     * @param UrlInterface $urlBuilder
     * @param Currency $currencyModel
     * @param SeQuraTranslationProvider $translation
     * @param UrlHelper $urlHelper
     */
    public function __construct(
        UrlInterface $urlBuilder,
        Currency $currencyModel,
        SeQuraTranslationProvider $translation,
        UrlHelper $urlHelper
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->currencyModel = $currencyModel;
        $this->translation = $translation;
        $this->urlHelper = $urlHelper;
    }

    /**
     * Modifies the "order_payment_additional" html element in order to inject addition SeQura payment information.
     *
     * @param Info $subject
     * @param string $result
     * @param string $childName
     *
     * @return string
     */
    public function afterGetChildHtml(Info $subject, $result, $childName): string
    {
        if ($childName === 'order_payment_additional') {
            try {
                $order = $this->getOrderService()->getOrderByShopReference($subject->getOrder()->getIncrementId());
                $result .= $this->getPaymentInformationHtml($order);
            } catch (OrderNotFoundException $e) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * Returns html string with SeQura payment information.
     *
     * @param SeQuraOrder $order
     *
     * @return string
     */
    private function getPaymentInformationHtml(SeQuraOrder $order): string
    {
        $paymentAmount = $this->getPaymentAmount($order);
        $paymentMethodName = $order->getPaymentMethod() ? $order->getPaymentMethod()->getName() : '/';
        $paymentMethodIcon = $order->getPaymentMethod() ? $order->getPaymentMethod()->getIcon() ?? '/' : '/';
        $sequraLink = $this->urlHelper->getBackendUrlForSequraOrder($order->getReference());

        $viewOnSeQuraButton = '';
        if ($order->getState() === OrderRequestStates::CONFIRMED) {
            // TODO: Look for an alternative to html_entity_decode
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $viewOnSeQuraButton = html_entity_decode('
                <a class="sequra-link" href="' . $sequraLink . '" target="_blank">
                  <button class="sequra-preview">' . $this->translation->translate("sequra.viewOnSequra") . '</button>
                </a>
            ');
        }

        // TODO: Look for an alternative to html_entity_decode
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        return html_entity_decode('
            <table class="sequra-table">
              <tr>
                <th>' . $this->translation->translate("sequra.paymentMethodLogo") . '</th>
                <th>' . $this->translation->translate("sequra.paymentMethod") . '</th>
                <th>' . $this->translation->translate("sequra.paymentAmount") . '</th>
              </tr>
              <tr>
                <td>' . $paymentMethodIcon . '</td>
                <td>' . $paymentMethodName . '</td>
                <td>' . $paymentAmount . '</td>
              </tr>
            </table>

            <div class="sequra-info-field">
              <div class="sequra-title">' . $this->translation->translate("sequra.status") . '</div>
              <div>' . $this->translation->translate("sequra.order") . ' ' . $this->translation->translate(
                  self::STATUS_MAP[$order->getState()]
              ) . '</div>
            </div>

            <div class="sequra-info-field">
              <div class="sequra-title">' . $this->translation->translate("sequra.sequraReferenceNumber") . '</div>
              <div>' . $order->getReference() . '</div>
            </div>

            <div class="sequra-info-field">
              <div class="sequra-title">' . $this->translation->translate("sequra.merchantId") . '</div>
              <div>' . $order->getMerchant()->getId() . '</div>
            </div>
        ') . $viewOnSeQuraButton;
    }

    /**
     * Get payment amount with currency.
     *
     * @param SeQuraOrder $order
     *
     * @return string
     */
    private function getPaymentAmount(SeQuraOrder $order): string
    {
        $amount = $order->getShippedCart()->getOrderTotalWithTax() + $order->getUnshippedCart()->getOrderTotalWithTax();
        $currency = $this->currencyModel->load($order->getUnshippedCart()->getCurrency())->getCurrencySymbol();

        return number_format($amount / 100, 2) . $currency;
    }

    /**
     * Returns an instance of Order service.
     *
     * @return OrderService
     */
    private function getOrderService(): OrderService
    {
        if (!isset($this->orderService)) {
            $this->orderService = ServiceRegister::getService(OrderService::class);
        }

        return $this->orderService;
    }
}
