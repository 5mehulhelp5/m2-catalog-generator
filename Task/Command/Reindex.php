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

use Qoliber\CatalogGenerator\Api\Task\TaskInterface;
use Qoliber\CatalogGenerator\Task\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class Reindex extends AbstractMagentoCommand
{
    /**
     * @return TaskInterface
     * @throws \Exception
     */
    public function runTask(): TaskInterface
    {
        $input = new ArrayInput(['command' => 'indexer:reindex']);
        $output = new BufferedOutput();

        $this->cli->doRun($input, $output);

        return $this;
    }
}
