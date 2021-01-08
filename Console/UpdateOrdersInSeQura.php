<?php
/**
 * Copyright © 2017 SeQura Engineering. All rights reserved.
 */

namespace Sequra\Core\Console;

use Sequra\Core\Model\ConfigFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to trigger DR report
 */
class UpdateOrdersInSeQura extends Command
{
    /**
     *  Command name
     */
    const NAME = 'sequra:updateorders';

    /**
     * Names of input arguments or options
     */
    const INPUT_KEY_ORDER = 'order';
    /**
     * Names of input arguments or options
     */
    const INPUT_KEY_LIMIT = 'limit';
    /**
     * Names of input arguments or options
     */
    protected $sequraOrders = null;

    /**
     * Configuration Object
     *
     * @var \Sequra\Core\Model\Config
     */
    protected $config;

    /**
     * OrderUpdater
     *
     * @var \Sequra\Core\Model\OrderUpdaterFactory
     */
    protected $orderUpdater;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * Constructor
     *
     * @param ConfigFactory $configFactory configFactory
     * @param \Sequra\Core\Model\OrderUpdaterFactory $orderUpdaterFactory reporteFactory
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        ConfigFactory $configFactory,
        \Sequra\Core\Model\OrderUpdaterFactory $orderUpdaterFactory,
        \Magento\Framework\App\State $state
    ) {
        $this->config = $configFactory->create();
        $this->orderUpdater = $orderUpdaterFactory->create();
        $this->state = $state;
        parent::__construct();
    }

    /**
     * Initialize triggerreport command
     *
     * @return void
     */
    protected function configure()
    {
        $this->addArgument(
            self::INPUT_KEY_ORDER,
            InputArgument::OPTIONAL,
            'First order Increment Id to update'
        );
        $this->addOption(
            self::INPUT_KEY_LIMIT,
            'l',
            InputOption::VALUE_OPTIONAL,
            'Limit number of orders to update'
        );
        $this->setName(self::NAME)
            ->setDescription('Send orders update to SeQura');

        parent::configure();
    }

    /**
     * Execute command.
     *
     * @param InputInterface  $input  InputInterface
     * @param OutputInterface $output OutputInterface
     *
     * @return                                        void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);
        $firstIncrementId = $input->getArgument(self::INPUT_KEY_ORDER)??0;
        $limit = $input->getOption(self::INPUT_KEY_LIMIT);

        $output->writeln('Updating orders from '.$firstIncrementId);
        $orderUpdated = $this->orderUpdater->sendOrderUpdates($firstIncrementId, $limit);
        $output->writeln($orderUpdated . ' Orders updated!');
    }
}
