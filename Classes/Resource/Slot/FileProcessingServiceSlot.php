<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Slot;

use Plan2net\FakeFal\Resource\Driver\LocalFakeDriver;
use Plan2net\FakeFal\Resource\Processing\Helper;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Service\FileProcessingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileProcessingServiceSlot
 * @package Plan2net\FakeFal\Resource\Slot
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class FileProcessingServiceSlot
{

    /**
     * @param FileProcessingService $service
     * @param DriverInterface $driver
     * @param ProcessedFile $processedFile
     * @param File $file
     * @param string $context
     * @param array $configuration
     */
    public function postProcessFile(
        FileProcessingService $service,
        DriverInterface $driver,
        ProcessedFile $processedFile,
        File $file,
        string $context,
        array $configuration
    ): void {
        if ($context === 'Image.CropScaleMask' &&
            $driver instanceof LocalFakeDriver &&
            $this->isFakeFile($file)) {
            $processedFilePath = $processedFile->getForLocalProcessing(false);
            $processedFileWidth = $processedFile->getProperty('width');
            $processedFileHeight = $processedFile->getProperty('height');

            if ($processedFileWidth &&
                $processedFileHeight &&
                @is_file($processedFilePath)) {
                /** @var Helper $processingHelper */
                $processingHelper = GeneralUtility::makeInstance(Helper::class);
                $processingHelper->writeDimensionsOnImage($processedFilePath, $processedFileWidth,
                    $processedFileHeight);
            }
        }
    }

    /**
     * @param File $file
     * @return bool
     */
    protected function isFakeFile(File $file): bool
    {
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');

        return (bool)$queryBuilder
            ->select('tx_fakefal_fake')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($file->getUid()))
            )
            ->execute()->fetchColumn();
    }

}
