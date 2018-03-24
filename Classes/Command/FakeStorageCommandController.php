<?php

namespace Plan2net\FakeFal\Command;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FakeStorageCommandController
 *
 * @package Plan2net\FakeFal\Command
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class FakeStorageCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
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
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance('TYPO3\CMS\Core\Database\ConnectionPool')->getQueryBuilderForTable('sys_file');
        $fileIdentifiers = $queryBuilder
            ->select('identifier')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter((int)$storageUid))
            )
            ->execute()->fetchAll(\PDO::FETCH_COLUMN);
        /** @var \TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory */
        $resourceFactory = \TYPO3\CMS\Core\Resource\ResourceFactory::getInstance();
        $storage = $resourceFactory->getStorageObject((int)$storageUid);
        /** @var \TYPO3\CMS\Core\Resource\File $file */
        foreach ($fileIdentifiers as $fileIdentifier) {
            $file = $storage->getFile($fileIdentifier);
            unset($file);
        }
    }
}
