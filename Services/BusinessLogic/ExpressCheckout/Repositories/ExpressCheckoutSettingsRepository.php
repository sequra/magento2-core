<?php

namespace Sequra\Core\Services\BusinessLogic\ExpressCheckout\Repositories;

use SeQura\Core\BusinessLogic\DataAccess\ExpressCheckout\Repositories\ExpressCheckoutSettingsRepository as Core;
use SeQura\Core\BusinessLogic\Domain\ExpressCheckout\Models\ExpressCheckoutSettings as CoreSettings;
use SeQura\Core\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Entities\ExpressCheckoutSettings as SettingsEntity;
use Sequra\Core\Services\BusinessLogic\ExpressCheckout\Models\ExpressCheckoutSettings as Settings;

/**
 * Saves express checkout settings without losing the stored merchant terms URL.
 */
class ExpressCheckoutSettingsRepository extends Core
{
    /**
     * @inheritDoc
     *
     * @throws QueryFilterInvalidParamException
     */
    public function setExpressCheckoutSettings(CoreSettings $settings): void
    {
        $existing = $this->getEntity();

        if (!$settings instanceof Settings) {
            $stored = $existing ? $existing->getExpressCheckoutSettings()->toArray() : [];
            $settings = new Settings(
                $settings->getExpressCheckoutConfigs(),
                $settings->getButtonStyle(),
                $stored[Settings::TERMS_AND_CONDITIONS_URL] ?? null
            );
        }

        $entity = new SettingsEntity();
        $entity->setStoreId($this->storeContext->getStoreId());
        $entity->setExpressCheckoutSettings($settings);

        if ($existing) {
            $entity->setId($existing->getId());
            $this->repository->update($entity);

            return;
        }

        $this->repository->save($entity);
    }
}
