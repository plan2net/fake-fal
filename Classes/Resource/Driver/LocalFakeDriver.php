<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Driver;

use Plan2net\FakeFal\Resource\Generator\ImageGeneratorFactory;
use Plan2net\FakeFal\Resource\Generator\ImageGeneratorInterface;
use Plan2net\FakeFal\Utility\Configuration;
use Plan2net\FakeFal\Utility\FileSignature;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LocalFakeDriver
 * @package Plan2net\FakeFal\Resource\Driver
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class LocalFakeDriver extends \TYPO3\CMS\Core\Resource\Driver\LocalDriver
{
    /**
     * Calculates the absolute path to this drivers storage location.
     * Creates the given base path directory if it does not exist.
     *
     * @param array $configuration
     * @return string
     * @throws InvalidConfigurationException
     * @throws InvalidPathException
     */
    protected function calculateBasePath(array $configuration): string
    {
        if (!array_key_exists('basePath', $configuration) || empty($configuration['basePath'])) {
            throw new InvalidConfigurationException(
                'Configuration must contain base path.',
                1346510477
            );
        }

        if (!empty($configuration['pathType']) && $configuration['pathType'] === 'relative') {
            $relativeBasePath = $configuration['basePath'];
            $absoluteBasePath = Environment::getPublicPath() . '/' . $relativeBasePath;
        } else {
            $absoluteBasePath = $configuration['basePath'];
        }
        $absoluteBasePath = $this->canonicalizeAndCheckFilePath($absoluteBasePath);
        $absoluteBasePath = rtrim($absoluteBasePath, '/') . '/';
        // Create directories instead of raising an exception
        if (!is_dir($absoluteBasePath)) {
            if (!mkdir($absoluteBasePath, 0777, true) && !is_dir($absoluteBasePath)) {
                throw new \RuntimeException(sprintf('Directory "%s" could not be created', $absoluteBasePath));
            }
        }

        $processingFolderPath = rtrim($absoluteBasePath, '/') . '/' . $this->getProcessingFolderForStorage();
        if (!is_dir($processingFolderPath)) {
            if (!mkdir($processingFolderPath, 0777, true) && !is_dir($processingFolderPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" could not be created', $path));
            }
        }

        return $absoluteBasePath;
    }

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
     * Checks if a file exists.
     * In case of the fake driver the file always exists and will be
     * created if it does not physically exist on disk
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
            } catch (\RuntimeException $e) {
                // unable to create directory
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
     * Returns a File object built from data in the sys_file table.
     * Usage of the ResourceFactory to get the file is impossible
     * as this would lead to endless recursion
     *
     * @param string $fileIdentifier
     * @return File|null
     */
    protected function getFileByIdentifier(string $fileIdentifier)
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
            ->execute()->fetch(\PDO::FETCH_ASSOC);
        if ($fileData) {
            /** @var ResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
            $file = $resourceFactory->createFileObject($fileData);
        }

        return $file;
    }

    /**
     * @param string $fileIdentifier
     * @return null|File
     * @throws InvalidPathException
     */
    protected function createFakeFile(string $fileIdentifier)
    {
        // create fake file only, if file data exists in the database
        $file = $this->getFileByIdentifier($fileIdentifier);
        if ($file && !$file->getStorage()->isWithinProcessingFolder($fileIdentifier)) {
            // check if parent directory exists, if not create it
            $absoluteFolderPath = $this->getAbsolutePath(dirname($file->getIdentifier()));
            if (!is_dir($absoluteFolderPath)) {
                $this->createFakeFolder($absoluteFolderPath);
            }
            // we can't use the $file->getXXX() methods as this would possibly lead to recursion
            $data = $this->getFileData($file);
            if ((int)$data['type'] === \TYPO3\CMS\Core\Resource\AbstractFile::FILETYPE_IMAGE) {
                $filePath = $this->createFakeImage($file);
            } else {
                $filePath = $this->createFakeDocument($file);
            }
            // set original modification date
            touch($filePath, $data['modification_date']);
            $this->markFileAsFake($file);
        }

        return $file;
    }

    /**
     * Create a fake folder when required,
     * except for default storage (0)
     *
     * @param string $absoluteFolderPath
     * @return void
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    protected function createFakeFolder(string $absoluteFolderPath)
    {
        if ($this->storageUid === 0) {
            throw new \UnexpectedValueException('Local default storage, no folder created');
        }

        try {
            GeneralUtility::mkdir_deep($absoluteFolderPath);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
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
        /** @var ImageGeneratorInterface $generator */
        $generator = ImageGeneratorFactory::create($generatorType);

        try {
            $filePath = $generator->generate($file, $filePath);
        } catch (\Exception $e) {
        }

        return $filePath;
    }

    /**
     * Create files with a valid file signature
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
            ->execute()->fetch(\PDO::FETCH_ASSOC);
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
     * @return mixed
     */
    protected function getProcessingFolderForStorage() {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_storage');
        $path = $queryBuilder
            ->select('processingfolder')
            ->from('sys_file_storage')
            ->where(
                $queryBuilder->expr()->eq('uid', (int)$this->storageUid)
            )
            ->execute()->fetch(\PDO::FETCH_COLUMN);

        // Assume '_processed_' as default
        return $path ?? '_processed_';
    }
}
