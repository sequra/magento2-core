<?php

namespace Sequra\Core\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use SeQura\Core\Infrastructure\ServiceRegister;
use SeQura\Core\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup;

class Index extends Action
{
    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * AbandonedCartRecords constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface|Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $this->getTaskRunnerWakeupService()->wakeup();
        $resultPage->getConfig()->getTitle()->prepend('Configuration');

        return $resultPage;
    }

    /**
     * Get TaskRunnerWakeup service.
     *
     * @return TaskRunnerWakeup
     */
    private function getTaskRunnerWakeupService(): TaskRunnerWakeup
    {
        return ServiceRegister::getService(TaskRunnerWakeup::class);
    }
}
