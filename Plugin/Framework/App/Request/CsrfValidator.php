<?php
namespace Sequra\Core\Plugin\Framework\App\Request;

class CsrfValidator
{
    public function aroundValidate($subject, $proceed, \Magento\Framework\App\RequestInterface $request, \Magento\Framework\App\ActionInterface $action)
    {
        if (
            $request instanceof \Magento\Framework\App\Request\Http &&
            $request->getRouteName() == 'sequra'
        ) {
            return true;
        }

        return $proceed($request, $action);
    }
}
