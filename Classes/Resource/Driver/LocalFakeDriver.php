<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Driver;

use Exception;
use PDO;
use Plan2net\FakeFal\Resource\Generator\ImageGeneratorFactory;
use Plan2net\FakeFal\Resource\Generator\ImageGeneratorInterface;
use Plan2net\FakeFal\Resource\Generator\LocalFakeImageGenerator;
use Plan2net\FakeFal\Utility\Configuration;
use Plan2net\FakeFal\Utility\FileSignature;
use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorageInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use UnexpectedValueException;

/**
 * Class LocalFakeDriver
 *
 * @package Plan2net\FakeFal\Resource\Driver
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class LocalFakeDriver extends LocalDriver
{
    /**
     * @param string $fileIdentifier
     * @param array $propertiesToExtract
     * @return array
     * @throws InvalidPathException
     */
    public function getFileInfoByIdentifier($fileIdentifier, array $propertiesToExtract = []): array
    {
        $absoluteFilePath = $this->getAbsolutePath($fileIdentifier);
        if (!file_exists($absoluteFilePath) || !is_file($absoluteFilePath)) {
            $this->createFakeFile($fileIdentifier);
        }

        return parent::getFileInfoByIdentifier($fileIdentifier, $propertiesToExtract);
    }

    /**
     * @param string $fileIdentifier
     * @return null|File
     * @throws InvalidPathException
     */
    protected function createFakeFile(string $fileIdentifier): ?File
    {
        // Create fake file only, if file data exists in the database
        $file = $this->getFileByIdentifier($fileIdentifier);
        if ($file && !$file->getStorage()->isWithinProcessingFolder($fileIdentifier)) {
            // Check if parent directory exists, if not create it
            $absoluteFolderPath = $this->getAbsolutePath(dirname($file->getIdentifier()));
            if (!is_dir($absoluteFolderPath)) {
                $this->createFakeFolder($absoluteFolderPath);
            }
            // Can't use the $file->getXXX() methods as this would possibly lead to recursion
            $data = $this->getFileData($file);
            if ((int)$data['type'] === AbstractFile::FILETYPE_IMAGE) {
                $filePath = $this->createFakeImage($file);
            } else {
                $filePath = $this->createFakeDocument($file);
            }
            // Set original modification date
            touch($filePath, $data['modification_date']);
            $this->markFileAsFake($file);
        }

        return $file;
    }

    /**
     * Returns a File object built from data in the sys_file table.
     * Usage of the ResourceFactory to get the file is impossible
     * as this would lead to endless recursion
     *
     * @param string $fileIdentifier
     * @return File|null
     */
    protected function getFileByIdentifier(string $fileIdentifier): ?File
    {
        $file = null;
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $fileData = $queryBuilder
            ->select('*')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter((int)$this->storageUid)),
                $queryBuilder->expr()->eq('identifier', $queryBuilder->createNamedParameter($fileIdentifier))
            )
            ->execute()->fetch(PDO::FETCH_ASSOC);
        if ($fileData) {
            /** @var ResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
            $file = $resourceFactory->createFileObject($fileData);
        }

        return $file;
    }

    /**
     * Creates a fake folder when required,
     * except for default storage (0)
     *
     * @param string $absoluteFolderPath
     * @return void
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    protected function createFakeFolder(string $absoluteFolderPath)
    {
        if ($this->storageUid === 0) {
            throw new UnexpectedValueException('Local default storage, no folder created');
        }

        try {
            GeneralUtility::mkdir_deep($absoluteFolderPath);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * @param File $file
     * @return array
     */
    protected function getFileData(File $file): array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');

        return $queryBuilder
            ->select('type', 'modification_date')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($file->getUid()))
            )
            ->execute()->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param File $file
     * @return string
     * @throws InvalidPathException
     */
    protected function createFakeImage(File $file): string
    {
        $filePath = $this->getAbsolutePath($file->getIdentifier());
        $generatorType = Configuration::getExtensionConfiguration('imageGeneratorType');
        if (empty($generatorType)) {
            $generatorType = LocalFakeImageGenerator::class;
        }
        /** @var ImageGeneratorInterface $generator */
        $generator = ImageGeneratorFactory::create($generatorType);

        try {
            $filePath = $generator->generate($file, $filePath);
        } catch (Exception $e) {
        }

        return $filePath;
    }

    /**
     * Creates files with a valid file signature
     *
     * @param File $file
     * @return string
     * @throws InvalidPathException
     */
    protected function createFakeDocument(File $file): string
    {
        $targetFilePath = $this->getAbsolutePath($file->getIdentifier());
        $fileSignature = FileSignature::getSignature($file->getExtension());
        if ($fileSignature) {
            $fp = fopen($targetFilePath, 'wb');
            fwrite($fp, $fileSignature);
            fclose($fp);
        }

        return $targetFilePath;
    }

    /**
     * @param File $file
     */
    protected function markFileAsFake(File $file)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $queryBuilder
            ->update('sys_file')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($file->getUid()))
            )
            ->set('tx_fakefal_fake', 1)
            ->execute();
    }

    /**
     * Checks if a file exists.
     * In case of the fake driver the file always exists and will be
     * created if it does not physically exist on disk.
     *
     * @param string $fileIdentifier
     * @return bool
     * @throws InvalidPathException
     */
    public function fileExists($fileIdentifier): bool
    {
        $absoluteFilePath = $this->getAbsolutePath($fileIdentifier);
        if (!is_file($absoluteFilePath)) {
            $file = $this->createFakeFile($fileIdentifier);
            if ($file === null) {
                // Unable to create file
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $folderIdentifier
     * @return bool
     * @throws InvalidPathException
     */
    public function folderExists($folderIdentifier): bool
    {
        $absoluteFolderPath = $this->getAbsolutePath($folderIdentifier);
        if (!is_dir($absoluteFolderPath)) {
            try {
                $this->createFakeFolder($absoluteFolderPath);
            } catch (RuntimeException $e) {
                // Unable to create directory
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $fileIdentifier
     * @param bool $writable
     * @return string
     * @throws InvalidPathException
     */
    public function getFileForLocalProcessing($fileIdentifier, $writable = true): string
    {
        $filePath = parent::getFileForLocalProcessing($fileIdentifier, $writable);
        // If the processing did not create a file
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
     * @param array $configuration
     * @return string
     * @throws InvalidConfigurationException
     * @throws InvalidPathException
     */
    protected function calculateBasePath(array $configuration): string
    {
        return $this->calculateBasePathAndCreateMissingDirectories($configuration);
    }

    /**
     * Calculates the absolute path to this drivers storage location.
     * Creates the given base path directory if it does not exist.
     *
     * @param array $configuration
     * @return string
     * @throws InvalidConfigurationException
     * @throws InvalidPathException
     */
    protected function calculateBasePathAndCreateMissingDirectories(array $configuration): string
    {
        if (!array_key_exists('basePath', $configuration) || empty($configuration['basePath'])) {
            throw new InvalidConfigurationException(
                'Configuration must contain base path.',
                1346510477
            );
        }

        if (!empty($configuration['pathType']) && $configuration['pathType'] === 'relative') {
            $relativeBasePath = $configuration['basePath'];
            $absoluteBasePath = $this->getPublicPath() . '/' . $relativeBasePath;
        } else {
            $absoluteBasePath = $configuration['basePath'];
        }
        $absoluteBasePath = $this->canonicalizeAndCheckFilePath($absoluteBasePath);
        $absoluteBasePath = rtrim($absoluteBasePath, '/') . '/';
        // Create directories instead of raising an exception
        if (!is_dir($absoluteBasePath)) {
            $this->createDirectory($absoluteBasePath);
        }

        $processingFolderPath = $this->getProcessingFolderForStorage($this->storageUid);
        // Check if this a relative or absolute path
        if (strpos($processingFolderPath, '/') !== 0) {
            $processingFolderPath = rtrim($absoluteBasePath, '/') . '/' . $processingFolderPath;
        }
        if (!is_dir($processingFolderPath)) {
            $this->createDirectory($processingFolderPath);
        }

        return $absoluteBasePath;
    }

    /**
     * @param int $storageId
     * @return string
     * @throws InvalidPathException
     */
    protected function getProcessingFolderForStorage(int $storageId): string
    {
        // Default storage
        if ($storageId === 0) {
            return 'typo3temp/assets/_processed_/';
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');
        $path = (string)$queryBuilder
            ->select('processingfolder')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('uid', $storageId)
            )
            ->execute()->fetchColumn(0);

        if (!empty($path)) {
            // Check if this is a combined folder path
            $parts = GeneralUtility::trimExplode(':', $path);
            if (count($parts) === 2) {
                // First part is the numeric storage ID
                $referencedStorageId = (int)$parts[0];
                $path = $this->getBasePathForStorage($referencedStorageId) .
                    $this->getProcessingFolderForStorage($referencedStorageId);
            }
        }

        return !empty($path) ? $path : ResourceStorageInterface::DEFAULT_ProcessingFolder;
    }

    /**
     * @param int $storageId
     * @return string
     * @throws InvalidPathException
     */
    protected function getBasePathForStorage(int $storageId): string
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');
        $configuration = (string)$queryBuilder
            ->select('configuration')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('uid', $storageId)
            )
            ->execute()->fetchColumn(0);

        $flexFormService = $this->getFlexFormService();
        $configuration = $flexFormService->convertFlexFormContentToArray($configuration);

        if (!empty($configuration['pathType']) && $configuration['pathType'] === 'relative') {
            $relativeBasePath = $configuration['basePath'];
            $absoluteBasePath = $this->getPublicPath() . '/' . $relativeBasePath;
        } else {
            $absoluteBasePath = $configuration['basePath'];
        }
        $absoluteBasePath = $this->canonicalizeAndCheckFilePath($absoluteBasePath);

        return rtrim($absoluteBasePath, '/') . '/';
    }

    /**
     * @param string $path
     */
    protected function createDirectory(string $path)
    {
        try {
            GeneralUtility::mkdir_deep($path);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
        if (!is_dir($path)) {
            throw new RuntimeException(sprintf('Directory "%s" could not be created', $path));
        }
    }

    /**
     * @return string
     * @deprecated
     */
    protected function getPublicPath(): string
    {
        if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 8007099) {
            return \TYPO3\CMS\Core\Core\Environment::getPublicPath();
        }

        return PATH_site; // deprecated
    }

    /**
     * @return mixed
     * @deprecated
     */
    protected function getFlexformService()
    {
        if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 8007099) {
            $flexFormService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Service\FlexFormService::class);
        } else {
            $flexFormService = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Service\FlexFormService::class);
        }

        return $flexFormService;
    }
}
