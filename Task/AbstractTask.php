<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */


declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Task;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Qoliber\CatalogGenerator\Sql\Connection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractTask
{
    /** @var \Symfony\Component\Console\Output\OutputInterface|null */
    protected ?OutputInterface $output = null;

    /**
     * @param \Magento\Framework\Filesystem\Io\File $ioFile
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Filesystem\Driver\File $file
     * @param \Qoliber\CatalogGenerator\Sql\Connection $connection
     * @param mixed[] $attributeData
     */
    public function __construct(
        protected IoFile $ioFile,
        protected Filesystem $filesystem,
        protected File $file,
        protected Connection $connection,
        protected array $attributeData = []
    ) {
    }

    /**
     * Set Output
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * Create a progress bar
     *
     * @param int $max
     * @return \Symfony\Component\Console\Helper\ProgressBar|null
     */
    protected function createProgressBar(int $max): ?ProgressBar
    {
        if (!$this->output || $max <= 0) {
            return null;
        }

        $progressBar = new ProgressBar($this->output, $max);
        $progressBar->setFormat('       %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% / ~%estimated:-6s% %memory:6s%');
        $progressBar->start();

        return $progressBar;
    }

    /**
     * Finish a progress bar
     *
     * @param \Symfony\Component\Console\Helper\ProgressBar|null $progressBar
     * @return void
     */
    protected function finishProgressBar(?ProgressBar $progressBar): void
    {
        if ($progressBar) {
            $progressBar->finish();
            $this->output?->writeln('');
        }
    }

    /**
     * Get Entity Type ID by entity type code
     *
     * @param string $entityTypeCode e.g. 'catalog_product', 'catalog_category'
     * @return int
     */
    protected function getEntityTypeId(string $entityTypeCode): int
    {
        return $this->connection->getEntityTypeId($entityTypeCode);
    }

    /**
     * Get Attribute Id
     *
     * @param int $entityType
     * @param string $attributeCode
     * @return int
     */
    public function getAttributeId(int $entityType, string $attributeCode): int
    {
        if (isset($this->attributeData[$entityType][$attributeCode])) {
            return $this->attributeData[$entityType][$attributeCode];
        }

        $sql = $this->connection->getConnection()->select()
            ->from($this->connection->getTableName('eav_attribute'), ['attribute_id'])
            ->where('entity_type_id = ?', $entityType)
            ->where('attribute_code = ?', $attributeCode);

        return (int) $this->connection->getConnection()->fetchOne($sql);
    }

    /**
     * Save File
     *
     * @param string $filePath
     * @param string $content
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function saveFile(string $filePath, string $content): void
    {
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $mediaPath = $media->getAbsolutePath();
        $info = $this->ioFile->getPathInfo($filePath);
        $directory = $info['dirname'];
        $fileName = $info['basename'];
        $fullPath = sprintf('%s%s', $mediaPath, $directory);
        $media->writeFile(sprintf('%s/%s', $fullPath, $fileName), $content);
    }
}
