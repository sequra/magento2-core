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
use Sequra\Core\Ui\Component\Listing\Column\SequraOrderLink;

/**
 * Class OrderDetails
 *
 * @package Sequra\Core\Plugin
 */
class OrderDetails
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Currency
     */
    protected $currencyModel;

    private const statusMap = [
        OrderRequestStates::CONFIRMED => 'paid',
        OrderRequestStates::ON_HOLD => 'pending review',
        OrderRequestStates::CANCELLED => 'cancelled',
    ];

    /**
     * @param UrlInterface $urlBuilder
     * @param Currency $currencyModel
     */
    public function __construct(UrlInterface $urlBuilder, Currency $currencyModel)
    {
        $this->urlBuilder = $urlBuilder;
        $this->currencyModel = $currencyModel;
    }

    /**
     * Modifies the "order_payment_additional" html element in order to inject addition SeQura payment information.
     *
     * @param Info $subject
     * @param $result
     * @param $childName
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
        $sequraLink = $this->urlBuilder->getUrl(SequraOrderLink::SEQURA_PORTAL_URL . $order->getReference());

        $viewOnSeQuraButton = '';
        if ($order->getState() === OrderRequestStates::CONFIRMED) {
            $viewOnSeQuraButton = html_entity_decode('
                <a class="sequra-link" href="' . $sequraLink . '" target="_blank">
                  <button class="sequra-preview">View on SeQura</button>
                </a>
            ');
        }

        return html_entity_decode('
            <table class="sequra-table">
              <tr>
                <th>Payment method logo</th>
                <th>Payment method</th>
                <th>Payment amount</th>
              </tr>
              <tr>
                <td>' . $paymentMethodIcon . '</td>
                <td>' . $paymentMethodName . '</td>
                <td>' . $paymentAmount . '</td>
              </tr>
            </table>

            <div class="sequra-info-field">
              <div class="sequra-title">Status</div>
              <div>Order ' . self::statusMap[$order->getState()] . '</div>
            </div>

            <div class="sequra-info-field">
              <div class="sequra-title">SeQura reference number</div>
              <div>' . $order->getReference() . '</div>
            </div>

            <div class="sequra-info-field">
              <div class="sequra-title">Merchant ID</div>
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
