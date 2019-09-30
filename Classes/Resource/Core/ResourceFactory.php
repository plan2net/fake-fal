<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Core;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

/**
 * Class ResourceFactory
 *
 * @package Plan2net\FakeFal\Resource\Core
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class ResourceFactory extends \TYPO3\CMS\Core\Resource\ResourceFactory
{
    /**
     * Copy from core's ResourceFactory,
     * for modifications see fake_fal comment inline
     *
     * @param string $input
     * @return File|FileInterface|Folder|null
     * @throws FileDoesNotExistException
     * @throws ResourceDoesNotExistException
     */
    public function retrieveFileOrFolderObject($input)
    {
        $input = str_replace($this->getPublicPath() . '/', '', $input);

        if (GeneralUtility::isFirstPartOfStr($input, 'file:')) {
            $input = substr($input, 5);

            return $this->retrieveFileOrFolderObject($input);
        }
        if (MathUtility::canBeInterpretedAsInteger($input)) {
            return $this->getFileObject($input);
        }
        if (strpos($input, ':') > 0) {
            list($prefix) = explode(':', $input);
            if (MathUtility::canBeInterpretedAsInteger($prefix)) {
                return $this->getObjectFromCombinedIdentifier($input);
            }
            if ($prefix === 'EXT') {
                $input = GeneralUtility::getFileAbsFileName($input);
                if (empty($input)) {
                    return null;
                }

                $input = PathUtility::getRelativePath($this->getPublicPath() . '/',
                        PathUtility::dirname($input)) . PathUtility::basename($input);

                return $this->getFileObjectFromCombinedIdentifier($input);
            }

            return null;
        }
        $input = PathUtility::getCanonicalPath(ltrim($input, '/'));
        // Fix core bug, value is url encoded
        $input = urldecode($input);
        // fake_fal: don't check for physical file here
        if (!empty($input) && $this->isFile($input)) {
            return $this->getFileObjectFromCombinedIdentifier($input);
        }

        return $this->getFolderObjectFromCombinedIdentifier($input);
    }

    /**
     * @return string
     * @deprecated
     */
    protected function getPublicPath(): string
    {
        if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 8007099) {
            return Environment::getPublicPath();
        }

        return PATH_site; // deprecated
    }

    /**
     * Find a sys_file entry by searching for matches
     * on the last part of the identifier
     *
     * @param string $path
     * @return bool
     */
    protected function isFile(string $path): bool
    {
        $originalPath = $path;
        /** @var ResourceStorage $storage */
        foreach ($this->getLocalStorages() as $storage) {
            // Remove possible path prefix
            $storageBasePath = rtrim($storage->getConfiguration()['basePath'], '/');
            $pathSite = $this->getPublicPath();
            if (strpos($storageBasePath, $pathSite) === 0) {
                $storageBasePath = substr($storageBasePath, strlen($pathSite));
            }
            if (strpos($originalPath, $storageBasePath) === 0) {
                $path = substr($originalPath, strlen($storageBasePath));
            }

            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
            if ((bool)$queryBuilder
                ->count('uid')
                ->from('sys_file')
                ->where(
                    $queryBuilder->expr()->like('identifier',
                        $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($path)))
                )->execute()->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ResourceStorage[]
     */
    protected function getLocalStorages(): array
    {
        /** @var StorageRepository $storageRepository */
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);

        return $storageRepository->findByStorageType('Local');
    }
}
