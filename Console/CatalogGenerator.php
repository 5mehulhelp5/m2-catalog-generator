<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Console;

use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\StateException;
use Qoliber\CatalogGenerator\Api\Service\CatalogGenerationServiceInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CatalogGenerator extends Command
{
    /** @var string  */
    public const NAME = 'qoliber:catalog:generate';

    /** @var string  */
    public const DESCRIPTION = 'Generate Product Catalog - based on YML file';

    /**
     * @param \Qoliber\CatalogGenerator\Api\Service\CatalogGenerationServiceInterface $catalogGenerationService
     */
    public function __construct(
        protected CatalogGenerationServiceInterface $catalogGenerationService,
        private readonly AppState $appState,
    ) {
        parent::__construct(self::NAME);
    }

    /**
     * Configure CLI command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Config file name'
            )
            ->setHelp('This command allows you to pass a required parameter.');
    }

    /**
     * Execute Command
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface  $input, OutputInterface $output): int
    {
        if (!$configFile = $input->getArgument('config')) {
            throw new \InvalidArgumentException('Config file name must be specified');
        }

        if ($this->appState->getMode() !== AppState::MODE_DEVELOPER) {
            throw new StateException(__(
                'This command is destructive. Ensure Magento is running in "developer" mode and try again.'
            ));
        }

        $this->catalogGenerationService
            ->setOutput($output)
            ->generate($configFile);

        return 0;
    }
}
