<?php

namespace Plan2net\FakeFal\Command;

/**
 * Class FakeStorageCommandController
 *
 * @package Plan2net\FakeFal\Command
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class FakeStorage67CommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
{
    /**
     * @param int $storageUid ID of the storage
     */
    public function initializeCommand($storageUid) {

    }

    /**
     * @param int $storageUid ID of the storage
     */
    public function createCommand($storageUid) {
        /** @var \TYPO3\CMS\Core\Database\DatabaseConnection $TYPO3_DB */
        global $TYPO3_DB;

        $result = $TYPO3_DB->sql_query('SELECT identifier FROM sys_file WHERE storage = ' . (int)$storageUid);
        $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
        $storage = $resourceFactory->getStorageObject((int)$storageUid);
        /** @var \TYPO3\CMS\Core\Resource\File $file */
        while (list($fileIdentifier) = $TYPO3_DB->sql_fetch_row($result)) {
            $file = $storage->getFile($fileIdentifier);
            unset($file);
        }
    }
}
