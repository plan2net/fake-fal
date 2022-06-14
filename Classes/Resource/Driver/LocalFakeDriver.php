<?php

declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Driver;

use Exception;
use PDO;
use Plan2net\FakeFal\Resource\Generator\ImageGeneratorFactory;
use Plan2net\FakeFal\Resource\Generator\LocalFakeImageGenerator;
use Plan2net\FakeFal\Utility\Configuration;
use Plan2net\FakeFal\Utility\FileSignature;
use RuntimeException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorageInterface;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

/**
 * Class LocalFakeDriver
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class LocalFakeDriver extends LocalDriver
{
    /**
     * @param string $fileIdentifier
     *
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
     *
     * @throws InvalidPathException
     */
    public function getFileContents($fileIdentifier): string
    {
        $absoluteFilePath = $this->getAbsolutePath($fileIdentifier);
        if (!file_exists($absoluteFilePath) || !is_file($absoluteFilePath)) {
            $this->createFakeFile($fileIdentifier);
        }

        return file_get_contents($absoluteFilePath) ?: '';
    }

    /**
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
            if (AbstractFile::FILETYPE_IMAGE === (int) $data['type']) {
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
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter((int) $this->storageUid)),
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
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    protected function createFakeFolder(string $absoluteFolderPath): void
    {
        if (0 === $this->storageUid) {
            throw new UnexpectedValueException('Local default storage, no folder created');
        }

        try {
            GeneralUtility::mkdir_deep($absoluteFolderPath);
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

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
     * @throws InvalidPathException
     */
    protected function createFakeImage(File $file): string
    {
        $filePath = $this->getAbsolutePath($file->getIdentifier());
        $generatorType = Configuration::getExtensionConfiguration('imageGeneratorType');
        if (empty($generatorType)) {
            $generatorType = LocalFakeImageGenerator::class;
        }
        $generator = ImageGeneratorFactory::create($generatorType);

        try {
            $filePath = $generator->generate($file, $filePath);
        } catch (Exception $e) {
            // Ignore
        }

        return $filePath;
    }

    /**
     * Creates files with a valid file signature
     *
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
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function markFileAsFake(File $file): void
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
     *
     * @throws InvalidPathException
     */
    public function fileExists($fileIdentifier): bool
    {
        if (empty($fileIdentifier)) {
            return false;
        }

        $absoluteFilePath = $this->getAbsolutePath($fileIdentifier);
        if (!is_file($absoluteFilePath)) {
            $file = $this->createFakeFile($fileIdentifier);
            if (null === $file) {
                // Unable to create file
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $folderIdentifier
     *
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
     * @param bool   $writable
     *
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
     * @throws InvalidConfigurationException
     * @throws InvalidPathException
     */
    protected function calculateBasePathAndCreateMissingDirectories(array $configuration): string
    {
        if (!array_key_exists('basePath', $configuration) || empty($configuration['basePath'])) {
            throw new InvalidConfigurationException('Configuration must contain base path.', 1346510477);
        }

        if (!empty($configuration['pathType']) && 'relative' === $configuration['pathType']) {
            $relativeBasePath = $configuration['basePath'];
            $absoluteBasePath = Environment::getPublicPath() . '/' . $relativeBasePath;
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
        if (0 !== strpos($processingFolderPath, '/')) {
            $processingFolderPath = rtrim($absoluteBasePath, '/') . '/' . $processingFolderPath;
        }
        if (!is_dir($processingFolderPath)) {
            $this->createDirectory($processingFolderPath);
        }

        return $absoluteBasePath;
    }

    /**
     * @throws InvalidPathException|\Doctrine\DBAL\DBALException
     */
    protected function getProcessingFolderForStorage(int $storageId): string
    {
        // Default storage
        if (0 === $storageId) {
            return 'typo3temp/assets/_processed_/';
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');
        $path = (string) $queryBuilder
            ->select('processingfolder')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('uid', $storageId)
            )
            ->execute()->fetchColumn(0);

        if (!empty($path)) {
            // Check if this is a combined folder path
            $parts = GeneralUtility::trimExplode(':', $path);
            if (2 === count($parts)) {
                // First part is the numeric storage ID
                $referencedStorageId = (int) $parts[0];
                $path = $this->getBasePathForStorage($referencedStorageId) .
                    $this->getProcessingFolderForStorage($referencedStorageId);
            }
        }

        return !empty($path) ? $path : ResourceStorageInterface::DEFAULT_ProcessingFolder;
    }

    /**
     * @throws InvalidPathException|\Doctrine\DBAL\DBALException
     */
    protected function getBasePathForStorage(int $storageId): string
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');
        $configuration = (string) $queryBuilder
            ->select('configuration')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('uid', $storageId)
            )
            ->execute()->fetchColumn(0);

        /** @var FlexFormService $flexFormService */
        $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        $configuration = $flexFormService->convertFlexFormContentToArray($configuration);

        if (!empty($configuration['pathType']) && 'relative' === $configuration['pathType']) {
            $relativeBasePath = $configuration['basePath'];
            $absoluteBasePath = Environment::getPublicPath() . '/' . $relativeBasePath;
        } else {
            $absoluteBasePath = $configuration['basePath'];
        }
        $absoluteBasePath = $this->canonicalizeAndCheckFilePath($absoluteBasePath);

        return rtrim($absoluteBasePath, '/') . '/';
    }

    protected function createDirectory(string $path): void
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
}
