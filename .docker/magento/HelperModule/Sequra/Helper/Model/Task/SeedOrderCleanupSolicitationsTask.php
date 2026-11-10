<?php

namespace Sequra\Helper\Model\Task;

use SeQura\Core\BusinessLogic\Domain\Order\Models\SeQuraOrder;
use SeQura\Core\BusinessLogic\Domain\Order\RepositoryContracts\SeQuraOrderRepositoryInterface;
use SeQura\Core\Infrastructure\ServiceRegister;

class SeedOrderCleanupSolicitationsTask extends Task
{
    private const STALE_AGE = 8 * 86400;
    private const FRESH_AGE = 3600;

    /**
     * Backdates a placed order and seeds a stale, a fresh and a linked solicitation from it.
     *
     * @param string[] $args
     *
     * @return array<string, mixed>
     */
    public function execute(array $args = [])
    {
        if (!isset($args['order_id'])) {
            $this->httpErrorResponse('Missing required argument: order_id', 400);
        }

        $this->initSeQuraServices();
        /** @var SeQuraOrderRepositoryInterface $repository */
        $repository = ServiceRegister::getService(SeQuraOrderRepositoryInterface::class);
        $shopOrder = $repository->getByShopReference((string) $args['order_id']);
        if ($shopOrder === null) {
            $this->httpErrorResponse('Order not found', 404);
        }

        $now = time();
        $shopOrder->setSolicitedAt($now - self::STALE_AGE);
        $repository->setSeQuraOrder($shopOrder);

        $references = [
            'shop_order' => $shopOrder->getReference(),
            'stale' => $this->saveSolicitation($repository, $shopOrder, 'stale', '', $now - self::STALE_AGE),
            'fresh' => $this->saveSolicitation($repository, $shopOrder, 'fresh', '', $now - self::FRESH_AGE),
            'linked' => $this->saveSolicitation(
                $repository,
                $shopOrder,
                'linked',
                'e2e-linked-' . $now,
                $now - self::STALE_AGE
            ),
        ];

        return ['success' => true, 'data' => $references];
    }

    /**
     * Saves a copy of the order as a solicitation of the given age and shop order.
     *
     * @param SeQuraOrderRepositoryInterface $repository
     * @param SeQuraOrder $source
     * @param string $kind
     * @param string $shopOrderReference
     * @param int $solicitedAt
     *
     * @return string
     */
    private function saveSolicitation(
        SeQuraOrderRepositoryInterface $repository,
        SeQuraOrder $source,
        string $kind,
        string $shopOrderReference,
        int $solicitedAt
    ): string {
        $reference = 'e2e-cleanup-' . $kind . '-' . bin2hex(random_bytes(6));
        $data = $source->toArray();
        unset($data['id']);
        /** @var SeQuraOrder $solicitation */
        $solicitation = SeQuraOrder::fromArray($data);
        $solicitation->setReference($reference)
            ->setCartId($reference)
            ->setOrderRef1($shopOrderReference)
            ->setState('')
            ->setSolicitedAt($solicitedAt);
        $repository->setSeQuraOrder($solicitation);

        return $reference;
    }
}
