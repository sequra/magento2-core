<?php

namespace Sequra\Core\Plugin\Framework\App\Request;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;

class CsrfValidator
{
    /**
     * Around validate plugin for CSRF validation
     *
     * @param mixed $subject The subject being validated
     * @param callable $proceed The original validate method
     * @param RequestInterface $request The request object
     * @param ActionInterface $action The action being performed
     * @return bool True if validation passes or is skipped
     */
    public function aroundValidate($subject, $proceed, RequestInterface $request, ActionInterface $action)
    {
        if ($request instanceof Http && $request->getRouteName() === 'sequra') {
            return true;
        }

        return $proceed($request, $action);
    }
}
