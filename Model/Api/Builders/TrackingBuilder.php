<?php

namespace Sequra\Core\Model\Api\Builders;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Shipping\Helper\Data as ShippingHelper;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Tracking\TrackingPostal;
use SeQura\Core\BusinessLogic\Utility\StringValidator;

/**
 * Builds the seQura trackings of an order out of the tracking records of all its shipments.
 */
class TrackingBuilder
{
    /**
     * @var ShipmentTrackRepositoryInterface
     */
    private $trackRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var ShippingHelper
     */
    private $shippingHelper;

    /**
     * @param ShipmentTrackRepositoryInterface $trackRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param ShippingHelper $shippingHelper
     */
    public function __construct(
        ShipmentTrackRepositoryInterface $trackRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        ShippingHelper $shippingHelper
    ) {
        $this->trackRepository = $trackRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->shippingHelper = $shippingHelper;
    }

    /**
     * Returns one postal tracking per tracking record of the order, read from the database.
     *
     * The records are read again instead of taken from the order, so a record deleted in this request is not sent,
     * and always in the same order, because integration-core compares the list with the last one sent by position.
     *
     * @param Order $order
     *
     * @return TrackingPostal[]
     */
    public function build(Order $order): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(ShipmentTrackInterface::ORDER_ID, $order->getEntityId())
            ->addSortOrder(
                $this->sortOrderBuilder
                    ->setField(ShipmentTrackInterface::ENTITY_ID)
                    ->setDirection(SortOrder::SORT_ASC)
                    ->create()
            )
            ->create();

        $trackings = [];
        foreach ($this->trackRepository->getList($searchCriteria)->getItems() as $track) {
            $trackings[] = new TrackingPostal(
                (string)$track->getEntityId(),
                $this->getCarrier($track),
                $this->getTrackNumber($track),
                null,
                $this->getTrackingUrl($track)
            );
        }

        return $trackings;
    }

    /**
     * Returns the carrier title of the record, falling back to its carrier code and then to the custom carrier.
     *
     * @param ShipmentTrackInterface $track
     *
     * @return string
     */
    private function getCarrier(ShipmentTrackInterface $track): string
    {
        foreach ([$track->getTitle(), $track->getCarrierCode()] as $carrier) {
            if (is_string($carrier) && trim($carrier) !== '') {
                return trim($carrier);
            }
        }

        return Track::CUSTOM_CARRIER_CODE;
    }

    /**
     * Returns the tracking number of the record, or null when it has none.
     *
     * @param ShipmentTrackInterface $track
     *
     * @return string|null
     */
    private function getTrackNumber(ShipmentTrackInterface $track): ?string
    {
        $trackNumber = $track->getTrackNumber();

        return is_string($trackNumber) && trim($trackNumber) !== '' ? trim($trackNumber) : null;
    }

    /**
     * Returns the Magento tracking popup URL of the record, or null when it is not a valid URL.
     *
     * @param ShipmentTrackInterface $track
     *
     * @return string|null
     */
    private function getTrackingUrl(ShipmentTrackInterface $track): ?string
    {
        if (!$track instanceof Track) {
            return null;
        }

        $url = $this->shippingHelper->getTrackingPopupUrlBySalesModel($track);

        return is_string($url) && StringValidator::isValidUrl($url) ? $url : null;
    }
}
