<?php

namespace Plan2net\FakeFal\Resource\Driver;

use Plan2net\FakeFal\Utility\FileSignature;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Class LocalDriver
 *
 * @package Plan2net\FakeFal\Resource\Driver
 * @author  Wolfgang Klinger <wk@plan2.net>
 */
class LocalDriver extends \TYPO3\CMS\Core\Resource\Driver\LocalDriver
{
    /**
     * @param string $fileIdentifier
     * @param array $propertiesToExtract
     * @return array
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = [])
    {
        $absoluteFilePath = $this->getAbsolutePath($fileIdentifier);
        if (!file_exists($absoluteFilePath) || !is_file($absoluteFilePath)) {
            $this->createFakeFile($fileIdentifier);
        }

        $dirPath = PathUtility::dirname($fileIdentifier);
        $dirPath = $this->canonicalizeAndCheckFolderIdentifier($dirPath);

        return $this->extractFileInformation($absoluteFilePath, $dirPath, $propertiesToExtract);
    }

    /**
     * Checks if a file exists.
     * In case of the fake driver the file always exists and will be
     * created if it does not physically exist on disk
     *
     * @param string $fileIdentifier
     * @return bool
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    public function fileExists($fileIdentifier)
    {
        $fileExists = false;

        $absoluteFilePath = $this->getAbsolutePath($fileIdentifier);
        if (is_file($absoluteFilePath)) {
            $fileExists = true;
        } else {
            $file = $this->createFakeFile($fileIdentifier);
            if ($file) {
                // if fake file created, return FALSE to trigger LocalCropScaleMaskHelper to write file dimensions on processed file, since the original file does not exist
                $fileExists = false;
            }
        }
        return $fileExists;
    }

    /**
     * @param string $fileIdentifier
     * @param bool $writable
     * @return string
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true)
    {
        $filePath = parent::getFileForLocalProcessing($fileIdentifier, $writable);
        // if the processing did not create a file
        // (e.g. because the original already has the right dimensions)
        // we have to check this here and create one if the original physical file
        // does not exist either
        if (!is_file($filePath)) {
            $file = $this->createFakeFile($fileIdentifier);
            if ($file) {
                $filePath = $this->getAbsolutePath($file->getIdentifier());
            }
        }

        return $filePath;
    }

    /**
     * @param string $fileIdentifier
     * @return null|\TYPO3\CMS\Core\Resource\File
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    protected function createFakeFile($fileIdentifier) {
        // create fake only, if file data exists in the database
        $file = $this->getFileByIdentifier($fileIdentifier);
        if ($file && !$file->getStorage()->isWithinProcessingFolder($fileIdentifier)) {
            // check if parent directory exists, if not create it
            $targetDirectoryPath = dirname($this->getAbsolutePath($file->getIdentifier()));
            if (!is_dir($targetDirectoryPath)) {
                GeneralUtility::mkdir_deep($targetDirectoryPath);
            }
            // we can't use the $file->getMimeType method,
            // as this would possibly lead to recursion
            $mimeType = $this->getFileMimeType($file);
            // in case it's an image, create a file with the right dimensions
            $modificationTime = $this->getFileModificationTime($file);
            if (strpos($mimeType, 'image') !== false) {
                $this->createFakeImage($file, $modificationTime);
                $this->markImageAsFake($file->getUid());
            }
            // otherwise just touch the file
            else {
                $targetFilePath = $this->getAbsolutePath($fileIdentifier);
                touch($targetFilePath, $modificationTime);
                GeneralUtility::fixPermissions($targetFilePath);
                $fileSignature = FileSignature::getSignature($file->getExtension());
                if ($fileSignature) {
                    file_put_contents($targetFilePath, $fileSignature);
                }
            }
        }

        return $file;
    }

    /**
     * @param string $fileIdentifier
     * @return \TYPO3\CMS\Core\Resource\File|null
     */
    protected function getFileByIdentifier($fileIdentifier)
    {
        $file = null;
        // we can't use the ResourceFactory to get the file,
        // as this would lead to endless recursion

        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance('TYPO3\CMS\Core\Database\ConnectionPool')->getQueryBuilderForTable('sys_file');
        $fileData = $queryBuilder
            ->select('*')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter((int)$this->storageUid)),
                $queryBuilder->expr()->eq('identifier', $queryBuilder->createNamedParameter($fileIdentifier))
            )
            ->execute()->fetch(\PDO::FETCH_ASSOC);
        /** @var \TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance('TYPO3\CMS\Core\Resource\ResourceFactory');
        if ($fileData) {
            $file = $resourceFactory->createFileObject($fileData);
        }

        return $file;
    }

    /**
     * @param int $fileUid
     * @return void
     */
    protected function markImageAsFake($fileUid)
    {
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance('TYPO3\CMS\Core\Database\ConnectionPool')->getQueryBuilderForTable('sys_file');
        $queryBuilder
            ->update('sys_file')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$fileUid))
            )
            ->set('tx_fakefal_fake', 1)
            ->execute();
    }

    /**
     * @param string $folderIdentifier
     * @return bool
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    public function folderExists($folderIdentifier)
    {
        $absoluteFilePath = $this->getAbsolutePath($folderIdentifier);
        if (!is_dir($absoluteFilePath)) {
            try {
                $this->createFakeFolder($folderIdentifier);
            }
            catch (\RuntimeException $e) {
                // unable to create directory
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $folderIdentifier
     * @throws \RuntimeException
     */
    protected function createFakeFolder($folderIdentifier)
    {
        try {
            $absolutePath = $this->getAbsolutePath($folderIdentifier);
            GeneralUtility::mkdir_deep($absolutePath);
        }
        catch (\Exception $e) {
            throw new \RuntimeException();
        }
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @param int $modificationTime
     * @throws \TYPO3\CMS\Core\Resource\Exception\InvalidPathException
     */
    protected function createFakeImage($file, $modificationTime) {
        /** @var MetaDataRepository $metaDataRepository */
        $metaDataRepository = GeneralUtility::makeInstance('TYPO3\CMS\Core\Resource\Index\MetaDataRepository');
        $metaData = $metaDataRepository->findByFile($file);
        $imageWidth = (int)$metaData['width'];
        $imageHeight = (int)$metaData['height'];
        $targetFilePath = $this->getAbsolutePath($file->getIdentifier());
        $params = '-size ' . $imageWidth . 'x' . $imageHeight . ' xc:lightgrey';
        $cmd = CommandUtility::imageMagickCommand('convert', $params . ' ' . escapeshellarg($targetFilePath));
        CommandUtility::exec($cmd);
        touch($targetFilePath, $modificationTime);
        GeneralUtility::fixPermissions($targetFilePath);
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return string
     */
    protected function getFileMimeType($file) {
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance('TYPO3\CMS\Core\Database\ConnectionPool')->getQueryBuilderForTable('sys_file');

        return $queryBuilder
            ->select('mime_type')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($file->getUid()))
            )
            ->execute()->fetchColumn(0);
    }

    /**
     * @param \TYPO3\CMS\Core\Resource\File $file
     * @return int
     */
    protected function getFileModificationTime($file) {
        /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance('TYPO3\CMS\Core\Database\ConnectionPool')->getQueryBuilderForTable('sys_file');

        return (int)$queryBuilder
            ->select('modification_date')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($file->getUid()))
            )
            ->execute()->fetchColumn(0);
    }

}
