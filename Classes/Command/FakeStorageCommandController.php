<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Command;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FakeStorageCommandController
 * @package Plan2net\FakeFal\Command
 * @author  Wolfgang Klinger <wk@plan2.net>
 * @author  Ioulia Kondratovitch <ik@plan2.net>
 */
class FakeStorageCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
{

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceFactory
     */
    protected $resourceFactory;

    public function __construct()
    {
        $this->resourceFactory = ResourceFactory::getInstance();
    }

    /**
     * Toggle fake mode for given storage(s).
     * Set storage to fake mode if currently not set and vica versa.
     *
     * @param string $storageIds Comma separated IDs of the target storages. If nothing provided, all available local storages will be affected.
     * @return void
     */
    public function toggleFakeModeCommand(string $storageIds = '')
    {
        $storages = $this->getAvailableLocalStorages();
        if (empty($storageIds)) {
            $storageIds = implode(',', $storages);
        }
        /** @var int $storageUid */
        foreach (GeneralUtility::intExplode(',', $storageIds) as $storageId) {
            if (in_array($storageId, $storages, true)) {
                try {
                    $this->deleteProcessedFiles($storageId);
                } catch (\Exception $e) {}
            }

            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_file_storage');
            $status = (int)$queryBuilder->select('tx_fakefal_enable')
                ->from('sys_file_storage')
                ->where(
                    $queryBuilder->expr()->eq('uid', $storageId)
                )->execute()->fetch(\PDO::FETCH_COLUMN);

            $queryBuilder->update('sys_file_storage')
                ->set('tx_fakefal_enable', $status === 1 ? 0 : 1)
                ->where(
                    $queryBuilder->expr()->eq('uid', $storageId)
                )->execute();
        }
    }

    /**
     * Create fake files within given storage(s).
     * Existing (real) files will be kept.
     *
     * @param string $storageIds Comma separated list of storage IDs
     * @param string $path Optional path
     * @return void
     */
    public function createFakeFilesCommand(string $storageIds, string $path = '')
    {
        $storages = $this->getAvailableFakeStorages();
        /** @var int $storageUid */
        foreach (GeneralUtility::intExplode(',', $storageIds) as $storageId) {
            if (in_array($storageId, $storages, true)) {
                $this->createFakeFiles($storageId, $path);
            }
        }
    }

    /**
     * Return a list of local storages (not in fake mode)
     *
     * @return array
     */
    protected function getAvailableLocalStorages(): array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');

        return $queryBuilder
            ->select('uid')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('driver', $queryBuilder->quote('Local')),
                $queryBuilder->expr()->eq('tx_fakefal_enable', 0)
            )
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Return a list of storage IDs set to fake mode
     *
     * @return array available storages matching type
     */
    protected function getAvailableFakeStorages(): array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');

        return $queryBuilder
            ->select('uid')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('driver', $queryBuilder->quote('Local')),
                $queryBuilder->expr()->eq('tx_fakefal_enable', 1)
            )
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Create fake files for given storage and all subfolders.
     * The subfolders will be created if they do not exist.
     * The created fake files will get value "1" set in field "tx_fakefal_fake"
     * in table sys_file.
     *
     * @param int $storageUid
     * @param string $path
     * @return void
     */
    protected function createFakeFiles(int $storageUid, string $path = '/')
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');

        /** @var string[] $fileIdentifiers */
        $fileIdentifiers = $queryBuilder
            ->select('identifier')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $storageUid),
                $queryBuilder->expr()->like('identifier',
                    $queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($path) . '%'))
            )
            ->execute()->fetchAll(\PDO::FETCH_COLUMN);

        /** @var \TYPO3\CMS\Core\Resource\ResourceStorage $storage */
        $storage = $this->resourceFactory->getStorageObject($storageUid);

        /** @var string $fileIdentifier */
        foreach ($fileIdentifiers as $fileIdentifier) {
            // Ask the storage to get the file,
            // this will create a fake file
            $file = $storage->getFile($fileIdentifier);
            unset($file);
        }
    }

    /**
     * Delete all processed files,
     * delete all records in sys_file_processedfiles
     * for given storage
     *
     * @param int $storageUid
     * @return void
     * @throws \TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException
     * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException
     * @throws \TYPO3\CMS\Core\Resource\Exception\InsufficientUserPermissionsException
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    protected function deleteProcessedFiles(int $storageUid)
    {
        $storage = $this->resourceFactory->getStorageObject($storageUid);
        $processingFolder = $storage->getProcessingFolder();

        // delete files and subfolders
        foreach ($processingFolder->getFiles() as $file) {
            $file->delete();
        }
        $subFolders = $storage->getFoldersInFolder($processingFolder);
        /** @var \TYPO3\CMS\Core\Resource\Folder $subFolder */
        foreach ($subFolders as $subFolder) {
            $storage->deleteFolder($subFolder, true);
        }

        // delete database entries
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_processedfile');
        $queryBuilder
            ->delete('sys_file_processedfile')
            ->where(
                $queryBuilder->expr()->eq('storage', $storageUid)
            )
            ->execute();
    }

}
