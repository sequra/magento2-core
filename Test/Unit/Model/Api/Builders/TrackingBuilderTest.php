<?php

namespace Sequra\Core\Test\Unit\Model\Api\Builders;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\Data\ShipmentTrackSearchResultInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\Track;
use Magento\Shipping\Helper\Data as ShippingHelper;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use SeQura\Core\BusinessLogic\Domain\Order\Models\OrderRequest\Tracking\TrackingPostal;
use Sequra\Core\Model\Api\Builders\TrackingBuilder;

/**
 * Class TrackingBuilderTest
 *
 * That every tracking record of the order becomes one postal tracking with the reference, carrier, number and
 * tracking URL seQura expects.
 */
class TrackingBuilderTest extends TestCase
{
    private const POPUP_URL = 'https://shop.example.com/shipping/tracking/popup?hash=abc';

    /**
     * @var Stub&ShippingHelper
     */
    private $shippingHelper;

    /**
     * A record becomes a postal tracking referenced by the track entity id.
     *
     * @return void
     */
    public function testBuildsOnePostalTrackingPerRecord(): void
    {
        $trackings = $this->builder([
            $this->track(15, 'DHL Express', 'dhl', 'JD0123'),
            $this->track(16, 'UPS', 'ups', '1Z999'),
        ])->build($this->order());

        self::assertCount(2, $trackings);
        self::assertContainsOnlyInstancesOf(TrackingPostal::class, $trackings);
        self::assertSame('15', $trackings[0]->getReference());
        self::assertSame('DHL Express', $trackings[0]->getCarrier());
        self::assertSame('JD0123', $trackings[0]->getTrackingNumber());
        self::assertSame(self::POPUP_URL, $trackings[0]->getTrackingUrl());
        self::assertSame('postal', $trackings[0]->getType());
        self::assertNull($trackings[0]->getDeliveredAt());
        self::assertSame('16', $trackings[1]->getReference());
    }

    /**
     * An order without tracking records gets an empty list, which clears the trackings at seQura.
     *
     * @return void
     */
    public function testBuildsAnEmptyListWhenTheOrderHasNoRecords(): void
    {
        self::assertSame([], $this->builder([])->build($this->order()));
    }

    /**
     * Without a title the carrier code is the carrier.
     *
     * @return void
     */
    public function testFallsBackToTheCarrierCode(): void
    {
        $trackings = $this->builder([$this->track(15, '', 'fedex', 'F1')])->build($this->order());

        self::assertSame('fedex', $trackings[0]->getCarrier());
    }

    /**
     * Without a title or a carrier code the record belongs to the custom carrier.
     *
     * @return void
     */
    public function testFallsBackToTheCustomCarrier(): void
    {
        $trackings = $this->builder([$this->track(15, null, null, 'F1')])->build($this->order());

        self::assertSame(Track::CUSTOM_CARRIER_CODE, $trackings[0]->getCarrier());
    }

    /**
     * A popup URL that is not a valid URL is left out rather than failing the tracking.
     *
     * @return void
     */
    public function testLeavesOutAnInvalidTrackingUrl(): void
    {
        $trackings = $this->builder([$this->track(15, 'DHL', 'dhl', 'JD0123')], 'not a url')
            ->build($this->order());

        self::assertNull($trackings[0]->getTrackingUrl());
    }

    /**
     * A record without a number is still sent, without one.
     *
     * @return void
     */
    public function testSendsARecordWithoutTrackingNumber(): void
    {
        $trackings = $this->builder([$this->track(15, 'DHL', 'dhl', '  ')])->build($this->order());

        self::assertNull($trackings[0]->getTrackingNumber());
    }

    /**
     * Builds the builder over the given tracking records of the order.
     *
     * @param Track[] $tracks
     * @param string $popupUrl
     *
     * @return TrackingBuilder
     */
    private function builder(array $tracks, string $popupUrl = self::POPUP_URL): TrackingBuilder
    {
        $searchResult = $this->createStub(ShipmentTrackSearchResultInterface::class);
        $searchResult->method('getItems')->willReturn($tracks);

        $repository = $this->createStub(ShipmentTrackRepositoryInterface::class);
        $repository->method('getList')->willReturn($searchResult);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->expects($this->once())->method('addFilter')->with('order_id', 42)->willReturnSelf();
        $searchCriteriaBuilder->expects($this->once())->method('addSortOrder')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($this->createStub(SearchCriteria::class));

        $sortOrderBuilder = $this->createMock(SortOrderBuilder::class);
        $sortOrderBuilder->expects($this->once())->method('setField')->with('entity_id')->willReturnSelf();
        $sortOrderBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_ASC)->willReturnSelf();
        $sortOrderBuilder->method('create')->willReturn($this->createStub(SortOrder::class));

        $this->shippingHelper = $this->createStub(ShippingHelper::class);
        $this->shippingHelper->method('getTrackingPopupUrlBySalesModel')->willReturn($popupUrl);

        return new TrackingBuilder($repository, $searchCriteriaBuilder, $sortOrderBuilder, $this->shippingHelper);
    }

    /**
     * Returns an order with entity id 42.
     *
     * @return Order
     */
    private function order(): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn(42);

        return $order;
    }

    /**
     * Returns a tracking record.
     *
     * @param int $entityId
     * @param string|null $title
     * @param string|null $carrierCode
     * @param string|null $trackNumber
     *
     * @return Track
     */
    private function track(int $entityId, ?string $title, ?string $carrierCode, ?string $trackNumber): Track
    {
        $track = $this->createStub(Track::class);
        $track->method('getEntityId')->willReturn($entityId);
        $track->method('getTitle')->willReturn($title);
        $track->method('getCarrierCode')->willReturn($carrierCode);
        $track->method('getTrackNumber')->willReturn($trackNumber);

        return $track;
    }
}
