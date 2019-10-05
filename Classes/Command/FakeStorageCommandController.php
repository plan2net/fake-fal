<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Command;

use Exception;
use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientUserPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Class FakeStorageCommandController
 *
 * @package Plan2net\FakeFal\Command
 * @author  Ioulia Kondratovitch <ik@plan2.net>
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class FakeStorageCommandController extends CommandController
{
    /**
     * @var ResourceFactory
     */
    protected $resourceFactory;

    public function __construct()
    {
        $this->resourceFactory = ResourceFactory::getInstance();
    }

    /**
     * Toggle mode of all or given storage(s)
     *
     * @param string $storageIdList Comma separated list of storage IDs
     * @return void
     */
    public function toggleFakeModeCommand(string $storageIdList = '')
    {
        $countAffected = 0;
        $localStorageIds = $storageIds = $this->getAvailableLocalStorageIds();
        if (!empty($storageIdList)) {
            $storageIds = explode(',', $storageIdList);
        }
        foreach ($storageIds as $storageId) {
            if (in_array($storageId, $localStorageIds, true)) {
                try {
                    $this->deleteProcessedFilesAndFolders($storageId);
                } catch (Exception $e) {
                }
            }

            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_file_storage');
            $status = (int)$queryBuilder->select('tx_fakefal_enable')
                ->from('sys_file_storage')
                ->where(
                    $queryBuilder->expr()->eq('uid', $storageId)
                )->execute()->fetchColumn(0);

            $countAffected += $queryBuilder->update('sys_file_storage')
                ->set('tx_fakefal_enable', $status === 1 ? 0 : 1)
                ->where(
                    $queryBuilder->expr()->eq('uid', $storageId)
                )->execute();
        }
        $this->output->output($countAffected . ' affected storages updated.' . PHP_EOL);
    }

    /**
     * Returns a list of local storage IDs
     *
     * @return array
     */
    protected function getAvailableLocalStorageIds(): array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_storage');

        return $this->getLocalStorageStatement($queryBuilder)->execute()->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param int $storageId
     * @return void
     * @throws FileOperationErrorException
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientUserPermissionsException
     * @throws InvalidPathException
     */
    protected function deleteProcessedFilesAndFolders(int $storageId)
    {
        $storage = $this->resourceFactory->getStorageObject($storageId);
        $processingFolder = $storage->getProcessingFolder();

        foreach ($processingFolder->getFiles() as $file) {
            $file->delete();
        }
        $subfolders = $storage->getFoldersInFolder($processingFolder);
        foreach ($subfolders as $folder) {
            $storage->deleteFolder($folder, true);
        }

        // Delete processed file database records
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_processedfile');
        $queryBuilder
            ->delete('sys_file_processedfile')
            ->where(
                $queryBuilder->expr()->eq('storage', $storageId)
            )
            ->execute();
    }

    /**
     * Create fake files within all storages, within given storage(s) or within given storage and path
     *
     * @param string $storageIdList Comma separated list of storage IDs
     * @param string $path Optional path
     * @return void
     */
    public function createFakeFilesCommand(string $storageIdList, string $path = '')
    {
        $localFakeStorageIds = $this->getAvailableLocalFakeStorageIds();
        /** @var int $storageUid */
        foreach (GeneralUtility::intExplode(',', $storageIdList) as $storageId) {
            if (in_array($storageId, $localFakeStorageIds, true)) {
                $this->createFakeFiles($storageId, $path);
            }
        }
    }

    /**
     * @return array
     */
    protected function getAvailableLocalFakeStorageIds(): array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_storage');

        $statement = $this->getLocalStorageStatement($queryBuilder);
        $statement->andWhere(
            $queryBuilder->expr()->eq('tx_fakefal_enable', 1)
        );

        return $statement->execute()->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return QueryBuilder
     */
    protected function getLocalStorageStatement(QueryBuilder $queryBuilder): QueryBuilder
    {
        return $queryBuilder
            ->select('uid')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('driver', $queryBuilder->quote('Local'))
            );
    }

    /**
     * @param int $storageId
     * @param string $path
     * @return void
     */
    protected function createFakeFiles(int $storageId, string $path = '/')
    {
        $storage = $this->resourceFactory->getStorageObject($storageId);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $fileIdentifiers = $queryBuilder
            ->select('identifier')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $storageId),
                $queryBuilder->expr()->like('identifier',
                    $queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($path) . '%'))
            )
            ->execute()->fetchAll(PDO::FETCH_COLUMN);

        foreach ($fileIdentifiers as $fileIdentifier) {
            // Require the storage to get the file,
            // this will create a fake file
            $file = $storage->getFile($fileIdentifier);
            unset($file);
        }
    }

    /**
     * List all storages
     *
     * @cli
     * @return void
     */
    public function listStoragesCommand(): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_storage');

        $storages = $queryBuilder->select('uid', 'name', 'driver', 'tx_fakefal_enable')
            ->from('sys_file_storage')
            ->execute()->fetchAll();

        foreach ($storages as &$storage) {
            $storage['tx_fakefal_enable'] = (bool)$storage['tx_fakefal_enable'] ? 'true' : 'false';
        }
        unset($storage);

        $this->output->outputTable($storages, ['uid', 'name', 'driver', 'is fake-storage?']);
    }

}
