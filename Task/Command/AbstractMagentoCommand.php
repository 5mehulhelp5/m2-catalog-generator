<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Task\Command;

use Magento\Framework\Console\Cli;
use Qoliber\CatalogGenerator\Api\Task\TaskInterface;

abstract class AbstractMagentoCommand implements TaskInterface
{
    public function __construct(
        protected readonly Cli $cli,
    ) {
    }

    abstract public function runTask(): TaskInterface;
}
