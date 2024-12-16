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

use Magento\Framework\App\Cache\TypeListInterface;
use Qoliber\CatalogGenerator\Api\Task\TaskInterface;

class CacheFlush implements TaskInterface
{
    public function __construct(
        private readonly TypeListInterface $cacheList,
    ) {
    }

    public function runTask(): TaskInterface
    {
        foreach ($this->cacheList->getTypes() as $cacheId => $cache) {
            $this->cacheList->cleanType($cacheId);
        }

        return $this;
    }
}
