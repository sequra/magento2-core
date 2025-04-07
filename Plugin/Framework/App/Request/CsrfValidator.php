<?php

namespace Sequra\Core\Plugin\Framework\App\Request;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;

class CsrfValidator
{
    public function aroundValidate($subject, $proceed, RequestInterface $request, ActionInterface $action)
    {
        if ($request instanceof Http && $request->getRouteName() === 'sequra') {
            return true;
        }

        return $proceed($request, $action);
    }
}
