<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Model\Api;

interface BuilderInterface
{
    public function build():BuilderInterface;
    public function addMerchantReferences():BuilderInterface;
    public function setState(string $state):BuilderInterface;
    public function setQuoteAsOrder(\Magento\Quote\Api\Data\CartInterface $quote):BuilderInterface;
    public function setOrder(\Magento\Sales\Api\Data\OrderInterface $order):BuilderInterface;
    public function setMerchantId(string $merchant_id):BuilderInterface;
    public function setLimit(?int $limit):BuilderInterface;
    public function setStoreId(int $storeId):BuilderInterface;
    public function getData():array;
}
