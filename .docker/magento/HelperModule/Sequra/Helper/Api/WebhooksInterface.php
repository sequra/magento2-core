<?php

namespace Sequra\Helper\Api;

interface WebhooksInterface
{
    /**
     * Execute the webhook.
     *
     * @return mixed
     */
    public function execute();
}
