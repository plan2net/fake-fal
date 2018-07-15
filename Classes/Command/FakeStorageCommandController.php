<?php

declare(strict_types=1);

namespace Plan2net\FakeFal\Command;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Utility\ArrayUtility;

/**
 * Class FakeStorageCommandController
 *
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

    /**
     * @var \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected $databaseConnection;


    public function __construct()
    {
        $this->resourceFactory = ResourceFactory::getInstance();
        $this->databaseConnection = $GLOBALS['TYPO3_DB'];
    }


    /**
     * set given storage(s) to LocalFake-mode: set driver-type to "LocalFake", backup information about original driver-type, clear processed files
     *
     * @param string $requestedStoragesUids comma separated IDs of the target real storages, default NULL. if nothing provided, all available real storages will be affected
     * @return void
     */
    public function setFakeModeCommand(string $requestedStoragesUids = '')
    {
        /** @var array $storagesUids */
        $storagesUids = $this->getMatchingStorages($requestedStoragesUids, 'real');

        /** @var int $storageUid */
        foreach ($storagesUids as $storageUid) {

            echo nl2br('storage with ID ' . $storageUid . ' will be set to LocalFake-mode ' . PHP_EOL);

            $this->deleteProcessedFiles($storageUid);

            // make backup of the driver-type of the storage:
            // @todo: rewrite the query using DBAL:
            /** @var string $query */
            $query = 'UPDATE sys_file_storage SET driver_original = driver WHERE uid = ' . $storageUid . ';';
            $this->databaseConnection->sql_query($query);

            // set the driver-type of the storage to "LocalFake":
            /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');
            $queryBuilder
                ->update('sys_file_storage')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($storageUid))
                )
                ->set('driver', 'LocalFake')
                ->execute();

            echo nl2br('storage with ID ' . $storageUid . ' has been set to LocalFake-mode ' . PHP_EOL);
        }
    }


    /**
     * set given storage(s) back to normal mode: restore driver-type, delete backup information about original driver-type, clear processed files, delete created fake files (optionally: keep fake files)
     *
     * @param string $requestedStoragesUids comma separated IDs of the target LocalFake storages, default NULL. if nothing provided, all available LocalFake storages will be affected
     * @param bool $keepFakeFiles if you want to keep fake files for all provided storages, provide TRUE; default: FALSE
     * @return void
     */
    public function setNormalModeCommand(string $requestedStoragesUids = '', bool $keepFakeFiles = false)
    {
        /** @var array $storagesUids */
        $storagesUids = $this->getMatchingStorages($requestedStoragesUids, 'fake');

        /** @var int $storageUid */
        foreach ($storagesUids as $storageUid) {

            echo nl2br('storage with ID ' . $storageUid . ' will be set to normal mode ' . PHP_EOL);

            if (!$keepFakeFiles) {
                // normal: delete fake files for given storage, processed files will be deleted automatically
                $this->deleteFakeFiles($storageUid);
            } else {
                // if TRUE for $keepFakeFiles is given, delete only the processed files
                echo nl2br('fake files for storage with ID ' . $storageUid . ' will be kept ' . PHP_EOL);
                $this->deleteProcessedFiles($storageUid);
            }

            // set the driver-type of the storage back to original:
            // @todo: rewrite the query using doctrine
            /** @var string $query1 */
            $query = 'UPDATE sys_file_storage  SET driver = driver_original WHERE uid = ' . $storageUid . ';';
            $this->databaseConnection->sql_query($query);

            // delete backup of the driver-type of the storage:
            /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');
            $queryBuilder
                ->update('sys_file_storage')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($storageUid))
                )
                ->set('driver_original', '')
                ->execute();

            echo nl2br('storage with ID ' . $storageUid . ' have been set to normal mode ' . PHP_EOL);
        }
    }


    /**
     * create fake files within given storage(s); the existing real files will be kept
     *
     * @param string $requestedStoragesUids comma separated IDs of the target LocalFake storages, default NULL. if nothing provided, all LocalFake storages will be affected
     * @return void
     */
    public function createFakesCommand($requestedStoragesUids = '')
    {
        /** @var array $storagesUids */
        $storagesUids = $this->getMatchingStorages($requestedStoragesUids, 'fake');

        /** @var string $path */
        $path = '/';

        /** @var int $storageUid */
        foreach ($storagesUids as $storageUid) {
            $this->createFakeFiles($storageUid, $path);
        }
    }


    /**
     * create fake files within given storage + given path; the existing real files will be kept
     *
     * @param int $storageUid ID of the target LocalFake storage
     * @param string $path path within storage; format: folder1/subfolder2/subfolder3
     * @return void
     */
    public function createFakesForPathCommand(int $storageUid, string $path)
    {
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');

        /** @var array $query */
        $fakeStorageUid = (int)$queryBuilder
            ->select('uid')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('driver', '"LocalFake"'),
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($storageUid))
            )
            ->execute()
            ->fetchColumn(0);

        $path = '/' . $path;

        if ($fakeStorageUid) {
            $this->createFakeFiles($fakeStorageUid, $path);
        } else {
            echo nl2br('requested action is not possible for given storage ' . PHP_EOL);
        }
    }


    /**
     * if you have any issues corresponding to processed files when you using extension fake_fal, use this command; the processed files and the records in sys_file_processedfiles for given storage(s) will be deleted
     *
     * @param string $requestedStoragesUids comma separated IDs of the target (LocalFake) storage(s), default NULL. if nothing provided, all available (LocalFake) storages will be affected
     * @param bool $ignoreDriverMode if you want to delete processed files of real storages as well, provide TRUE, default: FALSE
     * @return void
     */
    public function deleteProcessedFilesCommand(string $requestedStoragesUids = '', bool $ignoreDriverMode = false)
    {
        /** @var string $driverType */
        $driverType = 'fake';
        if ($ignoreDriverMode) {
            $driverType = 'all';
        }

        /** @var array $storagesUids */
        $storagesUids = $this->getMatchingStorages($requestedStoragesUids, $driverType);

        /** @var int $storageUid */
        foreach ($storagesUids as $storageUid) {
            $this->deleteProcessedFiles($storageUid);
        }
    }


    /**
     * secure remove the created fake files from given (LocalFake) storage(s); only fake files well be deleted, the real files and the records in sys_file will be kept
     *
     * @param string $requestedStoragesUids comma separated IDs of the target LocalFake storages, default NULL. if nothing provided, all available LocalFake storages will be affected
     * @param bool $ignoreDriverMode if you have any issues corresponding to fake files existing in real storages, provide TRUE, default: FALSE
     * @return void
     */
    public function deleteFakesCommand(string $requestedStoragesUids = '', bool $ignoreDriverMode = false)
    {
        /** @var string $driverType */
        $driverType = 'fake';
        if ($ignoreDriverMode) {
            $driverType = 'all';
        }

        /** @var array $storagesUids */
        $storagesUids = $this->getMatchingStorages($requestedStoragesUids, $driverType);

        /** @var int $storageUid */
        foreach ($storagesUids as $storageUid) {
            $this->deleteFakeFiles($storageUid);
        }
    }


    /**
     * this functon provides IDs of all available storages, found in table sys_file_storage, matching requested type
     * fake = "LocalFake", real != "LocalFake", all = all types
     *
     * @param string $type
     * @return array available storages matching type
     */
    private function getAvailableStorages(string $type): array
    {
        /** @var array $storages */
        $storages = [];

        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');

        switch ($type) {
            case 'fake':
                $storages = $queryBuilder
                    ->select('uid')
                    ->from('sys_file_storage')
                    ->where(
                        $queryBuilder->expr()->eq('driver', '"LocalFake"')
                    )
                    ->execute()
                    ->fetchAll(\PDO::FETCH_COLUMN);
                break;
            case 'real':
                $storages = $queryBuilder
                    ->select('uid')
                    ->from('sys_file_storage')
                    ->where(
                        $queryBuilder->expr()->notLike('driver', '"LocalFake"')
                    )
                    ->execute()
                    ->fetchAll(\PDO::FETCH_COLUMN);
                break;
            case 'all':
                $storages = $queryBuilder
                    ->select('uid')
                    ->from('sys_file_storage')
                    ->execute()
                    ->fetchAll(\PDO::FETCH_COLUMN);
                break;
        }

        return $storages;
    }


    /**
     * this function compares the list of requested storages with the list of available storages (matching allowed type)
     * and returns the list of matching storages, which will be processed further
     *
     * @param string $requestedStoragesUids comma separated IDs of the requested storages
     * @param string $type
     * @return array available storages matching allowed type and request
     */
    private function getMatchingStorages(string $requestedStoragesUids, string $type): array
    {
        /** @var array $result */
        $result = [];

        /** @var array $availableStoragesUids */
        $availableStoragesUids = $this->getAvailableStorages($type);

        if ($requestedStoragesUids) {
            /** @var array $requestedStoragesArr */
            $requestedStoragesArr = ArrayUtility::integerExplode(',', $requestedStoragesUids);

            /** @var array $foundStoragesUids */
            $foundStoragesUids = array_values(array_intersect($requestedStoragesArr, $availableStoragesUids));

            asort($foundStoragesUids);
            $result = $foundStoragesUids;
        } else {
            $result = $availableStoragesUids;
        }

        if (!empty($result)) {
            echo nl2br('proceed with storages ' . implode(', ', $result) . ' ' . PHP_EOL);
        } else {
            echo nl2br('requested action is not possible for given storages ' . PHP_EOL);
        }

        return $result;
    }


    /**
     * this function creates fake files for given storage and all subfolders. the subfolders will be createtd as well if not exist.
     * the created fake files will get value "1" in field "tx_fakefal_fake" in table sys_file
     *
     * @param int $storageUid
     * @param string $path
     * @return void
     */
    private function createFakeFiles(int $storageUid, string $path)
    {
        // @todo rearrange variable $path to be able to use $queryBuilder->createNamedParameter if possible

        if (empty($path)) {
            $path = '/';
        }

        echo nl2br('fake files for driver with uid ' . $storageUid . ' and type "LocalFake" and path "' . $path . '" will be created ' . PHP_EOL);

        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');

        /** @var array $fileIdentifiers */
        $fileIdentifiers = $queryBuilder
            ->select('identifier')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter($storageUid)),
                $queryBuilder->expr()->like('identifier', '"' . $path . '%"')
            )
            ->execute()->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($fileIdentifiers)) {
            echo nl2br('no entries found in the database for storage  ' . $storageUid . ' and path "' . $path . '" in the table "sys_file". Check the ID of storage and spelling of the path (eg subfolder1/subfolder2/subfolder3) ' . PHP_EOL);
        } else {
            /** @var \TYPO3\CMS\Core\Resource\ResourceStorage $storage */
            $storage = $this->resourceFactory->getStorageObject($storageUid);

            /** @var int $fileIdentifier */
            foreach ($fileIdentifiers as $fileIdentifier) {
                /** @var \TYPO3\CMS\Core\Resource\File $file */
                $file = $storage->getFile($fileIdentifier);
                unset($file);
            }

            echo nl2br('fake files for driver with uid ' . $storageUid . ' and type "LocalFake" and path "' . $path . '" have been created ' . PHP_EOL);
        }
    }


    /**
     * this function deletes all processed files for given storage and deletes all records in sys_file_processedfiles for given storage
     *
     * @param int $storageUid
     * @return void
     */
    private function deleteProcessedFiles(int $storageUid)
    {
        echo nl2br('processed files for storage with ID ' . $storageUid . ' will be deleted ' . PHP_EOL);

        /** @var \TYPO3\CMS\Core\Resource\ResourceStorage $storage */
        $storage = $this->resourceFactory->getStorageObject($storageUid);

        /** @var \TYPO3\CMS\Core\Resource\Folder $processingFolder */
        $processingFolder = $storage->getProcessingFolder();

        /** @var array $subFolders */
        $subFolders = $storage->getFoldersInFolder($processingFolder);

        /** @var \TYPO3\CMS\Core\Resource\Folder $subFolder */
        foreach ($subFolders as $subFolder) {
            $storage->deleteFolder($subFolder, true);
        }

        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_processedfile');
        $queryBuilder
            ->delete('sys_file_processedfile')
            ->where(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter($storageUid))
            )
            ->execute();

        unset($storage);
        echo nl2br('processed files for storage with ID ' . $storageUid . ' have been deleted ' . PHP_EOL);
    }


    /**
     * this function deletes all files which ar marked as "tx_fakefal_fake" in table sys_file for given storage and resets the marker "tx_fakefal_fake" to 0.
     * the records in the table sys_file will be kept
     *
     * @param int $storageUid
     * @return void
     */
    private function deleteFakeFiles(int $storageUid)
    {
        echo nl2br('fake files for storage with ID ' . $storageUid . ' will be deleted ' . PHP_EOL);

        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');

        // get files which are marked as fake:
        /** @var array $fileIdentifiers */
        $fileIdentifiers = $queryBuilder
            ->select('identifier')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter($storageUid)),
                $queryBuilder->expr()->eq('tx_fakefal_fake', 1)
            )
            ->execute()->fetchAll(\PDO::FETCH_COLUMN);

        /** @var string $fileIdentifier */
        foreach ($fileIdentifiers as $fileIdentifier) {
            /** @var \TYPO3\CMS\Core\Resource\ResourceStorage $storage */
            $storage = $this->resourceFactory->getStorageObject($storageUid);

            /** @var \TYPO3\CMS\Core\Resource\File $file */
            $file = $storage->getFile($fileIdentifier);

            // delete the file, but keep the record in sys_file table, update the file record: set fake to 0:
            if (unlink($file->getForLocalProcessing(false))) {
                $queryBuilder
                    ->update('sys_file')
                    ->where(
                        $queryBuilder->expr()->eq('uid',
                            $queryBuilder->createNamedParameter((int)$file->getUid()))
                    )
                    ->set('tx_fakefal_fake', 0)
                    ->execute();
            }
        }
        echo nl2br('fake files for storage with ID ' . $storageUid . ' have benn deleted ' . PHP_EOL);

        $this->deleteProcessedFiles($storageUid);
    }

}
