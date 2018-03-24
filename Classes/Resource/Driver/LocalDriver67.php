<?php

namespace Plan2net\FakeFal\Resource\Driver;

use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LocalDriver
 *
 * @package Plan2net\FakeFal\Resource\Driver
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class LocalDriver67 extends LocalDriver
{
    /**
     * @param string $fileIdentifier
     * @return \TYPO3\CMS\Core\Resource\File|null
     */
    protected function getFileByIdentifier($fileIdentifier)
    {
        $file = null;
        // we can't use the ResourceFactory to get the file,
        // as this would lead to endless recursion

        /** @var \TYPO3\CMS\Core\Database\DatabaseConnection $TYPO3_DB */
        global $TYPO3_DB;
        $result = $TYPO3_DB->sql_query('SELECT * FROM sys_file WHERE storage = ' . (int)$this->storageUid . ' AND identifier = ' . $TYPO3_DB->fullQuoteStr($fileIdentifier, 'sys_file'));
        $fileData = $TYPO3_DB->sql_fetch_assoc($result);

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance('TYPO3\CMS\Core\Resource\ResourceFactory');
        if ($fileData) {
            $file = $resourceFactory->createFileObject($fileData);
        }

        return $file;
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return string
     */
    protected function getFileMimeType($file) {
        /** @var \TYPO3\CMS\Core\Database\DatabaseConnection $TYPO3_DB */
        global $TYPO3_DB;
        $result = $TYPO3_DB->sql_query('SELECT mime_type FROM sys_file WHERE uid = ' . (int)$file->getUid());
        list($mimeType) = $TYPO3_DB->sql_fetch_row($result);

        return $mimeType;
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return int
     */
    protected function getFileModificationTime($file) {
        /** @var \TYPO3\CMS\Core\Database\DatabaseConnection $TYPO3_DB */
        global $TYPO3_DB;
        $result = $TYPO3_DB->sql_query('SELECT modification_date FROM sys_file WHERE uid = ' . (int)$file->getUid());
        list($modificationTime) = $TYPO3_DB->sql_fetch_row($result);

        return (int)$modificationTime;
    }

}
