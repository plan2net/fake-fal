<?php

namespace Plan2net\FakeFal\Resource\Processing;

use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LocalCropScaleMaskHelper
 *
 * @package Plan2net\FakeFal\Resource\Processing
 * @author  Wolfgang Klinger <wk@plan2.net>
 * @author  Ioulia Kondratovitch <ik@plan2.net>
 */
class LocalCropScaleMaskHelper extends \TYPO3\CMS\Core\Resource\Processing\LocalCropScaleMaskHelper
{
    /**
     * @param \TYPO3\CMS\Core\Resource\Processing\TaskInterface $task
     * @return array|null
     */
    public function process(TaskInterface $task)
    {
        $driverType = $task->getSourceFile()->getStorage()->getDriverType();

        // proceed with fake-magic only if the driver type is LocalFake:
        if ($driverType === 'LocalFake') {

            /** @var  \TYPO3\CMS\Core\Resource\File $sourceFile */
            $sourceFile = $task->getSourceFile();
            /** @var \TYPO3\CMS\Core\Resource\ResourceStorage $storage */
            $storage = $sourceFile->getStorage();
            /** @var string $fileIdentifier */
            $fileIdentifier = $sourceFile->getIdentifier();

            // force driver LocalFake to create the fake-file first if original file is missing, before creating processed files
            $storage->hasFile($fileIdentifier);

            // then evaluate if the file is original or fake
            /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance('TYPO3\CMS\Core\Database\ConnectionPool')->getQueryBuilderForTable('sys_file');
            /** @var int $isFakeFile */
            $isFakeFile = $queryBuilder
                ->select('tx_fakefal_fake')
                ->from('sys_file')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$sourceFile->getUid()))
                )
                ->execute()->fetchColumn();

            $result = parent::process($task);

            // if the result is empty, we try to use the original file
            if (empty($result) && $task->getSourceFile() &&
                @is_file($task->getSourceFile()->getForLocalProcessing(false))) {
                $result = [
                    'filePath' => $task->getSourceFile()->getForLocalProcessing(false),
                    'width' => $task->getSourceFile()->getProperty('width'),
                    'height' => $task->getSourceFile()->getProperty('height')
                ];
            }

            // write diemensions only if it is a fake file:
            if ($isFakeFile) {
                /** @var \Plan2net\FakeFal\Resource\Processing\Helper $processingHelper */
                $processingHelper = GeneralUtility::makeInstance('Plan2net\FakeFal\Resource\Processing\Helper');
                $processingHelper->writeDimensionsOntoImage($result['filePath'], $result['width'], $result['height']);
            }

        } else {
            // just process normally if it is not LocalFake driver:
            $result = parent::process($task);
        }

        return $result;
    }
}