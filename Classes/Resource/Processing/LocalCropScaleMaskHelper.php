<?php

namespace Plan2net\FakeFal\Resource\Processing;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LocalCropScaleMaskHelper
 *
 * @package Plan2net\FakeFal\Resource\Processing
 * @author  Wolfgang Klinger <wk@plan2.net>
 * @author  Ioulia Kondratovitch <ik@plan2.net>
 * @author  Martin Kutschker <mk@plan2.net>
 */
class LocalCropScaleMaskHelper extends \TYPO3\CMS\Core\Resource\Processing\LocalCropScaleMaskHelper
{
    /**
     * @param TaskInterface $task
     * @return array|null
     */
    public function process(TaskInterface $task)
    {
        $driverType = $task->getSourceFile()->getStorage()->getDriverType();

        // proceed with fake-magic only if the driver type is LocalFake:
        if ($driverType === 'LocalFake') {
            $this->createFileIfMissing($task->getSourceFile());
        }

        $result = parent::process($task);

        if ($driverType === 'LocalFake') {
            $result = $this->updateImage($result, $task->getSourceFile());
        }

        return $result;
    }

    /**
     * @param File $sourceFile
     * @return void
     */
    protected function createFileIfMissing(File $sourceFile)
    {
        /** @var \TYPO3\CMS\Core\Resource\ResourceStorage $storage */
        $storage = $sourceFile->getStorage();
        /** @var string $fileIdentifier */
        $fileIdentifier = $sourceFile->getIdentifier();

        // force driver LocalFake to create the fake-file first if original file is missing, before creating processed files
        $storage->hasFile($fileIdentifier);
    }

    /**
     * @param array|null $result
     * @param File  $sourceFile
     * @return array
     */
    protected function updateImage($result, File $sourceFile)
    {
        if (version_compare(TYPO3_version, '8.7', '<') === true) {
            $isFakeFile = $this->isFakeFile67((int)$sourceFile->getUid());
        }
        else {
            $isFakeFile = $this->isFakeFile((int)$sourceFile->getUid());
        }

        // if the result is empty (ie unprocessed by TYPO3), we try to use the original file
        if (empty($result) && $sourceFile && @is_file($sourceFile->getForLocalProcessing(false))) {
            $result = [
                'filePath' => $sourceFile->getForLocalProcessing(false),
                'width' => $sourceFile->getProperty('width'),
                'height' => $sourceFile->getProperty('height'),
            ];
        }

        // write dimensions only if it is a fake file
        if ($isFakeFile) {
            /** @var \Plan2net\FakeFal\Resource\Processing\Helper $processingHelper */
            $processingHelper = GeneralUtility::makeInstance('Plan2net\FakeFal\Resource\Processing\Helper');
            $processingHelper->writeDimensionsOntoImage($result['filePath'], $result['width'], $result['height']);
        }

        return $result;
    }

    /**
     * @param int $fileUid
     * @return bool
     */
    protected function isFakeFile($fileUid)
    {
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance('TYPO3\CMS\Core\Database\ConnectionPool')->getQueryBuilderForTable('sys_file');
        return (bool)$queryBuilder
            ->select('tx_fakefal_fake')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$fileUid))
            )
            ->execute()->fetchColumn();
    }

    /**
     * @param int $fileUid
     * @return bool
     */
    protected function isFakeFile67($fileUid)
    {
        /** @var \TYPO3\CMS\Core\Database\DatabaseConnection $TYPO3_DB */
        global $TYPO3_DB;
        $result = $TYPO3_DB->sql_query('SELECT tx_fakefal_fake FROM sys_file WHERE uid = ' . (int)$fileUid);
        list($isFakeFile) = $TYPO3_DB->sql_fetch_row($result);

        return (bool)$isFakeFile;
    }
}