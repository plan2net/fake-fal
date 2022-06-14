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
use function explode;
use function ltrim;
use function str_replace;
use function strpos;
use function substr;

/**
 * Class ResourceFactory
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class ResourceFactory extends \TYPO3\CMS\Core\Resource\ResourceFactory
{
    /**
     * Copy from core's ResourceFactory,
     * for modifications see fake_fal comment inline
     *
     * @param string $input
     *
     * @throws FileDoesNotExistException
     * @throws ResourceDoesNotExistException
     *
     * @return File|FileInterface|Folder|null
     */
    public function retrieveFileOrFolderObject($input)
    {
        // Remove Environment::getPublicPath() because absolute paths under Windows systems contain ':'
        // This is done in all considered sub functions anyway
        $input = str_replace(Environment::getPublicPath() . '/', '', $input);

        if (GeneralUtility::isFirstPartOfStr($input, 'file:')) {
            $input = substr($input, 5);

            return $this->retrieveFileOrFolderObject($input);
        }
        if (MathUtility::canBeInterpretedAsInteger($input)) {
            return $this->getFileObject($input);
        }
        if (strpos($input, ':') > 0) {
            [$prefix] = explode(':', $input);
            if (MathUtility::canBeInterpretedAsInteger($prefix)) {
                // path or folder in a valid storageUID
                return $this->getObjectFromCombinedIdentifier($input);
            }
            if ('EXT' === $prefix) {
                $input = GeneralUtility::getFileAbsFileName($input);
                if (empty($input)) {
                    return null;
                }

                $input = PathUtility::getRelativePath(Environment::getPublicPath() . '/', PathUtility::dirname($input)) . PathUtility::basename($input);

                return $this->getFileObjectFromCombinedIdentifier($input);
            }

            return null;
        }
        // this is a backwards-compatible way to access "0-storage" files or folders
        // eliminate double slashes, /./ and /../
        $input = PathUtility::getCanonicalPath(ltrim($input, '/'));
        // Fix core bug, value is url encoded
        $input = urldecode($input);
        // fake_fal: check for physical file (e.g. temporary assets like online media preview images)
        // and for files available in the database (that should be created)
        if (!empty($input) && (@is_file(Environment::getPublicPath() . '/' . $input) || $this->isFile($input))) {
            return $this->getFileObjectFromCombinedIdentifier($input);
        }

        return $this->getFolderObjectFromCombinedIdentifier($input);
    }

    protected function isFile(string $path): bool
    {
        $originalPath = $path;
        foreach ($this->getLocalStorages() as $storage) {
            // Remove possible path prefix
            $storageBasePath = rtrim($storage->getConfiguration()['basePath'], '/');
            $pathSite = Environment::getPublicPath();
            if (0 === strpos($storageBasePath, $pathSite)) {
                $storageBasePath = substr($storageBasePath, strlen($pathSite));
            }
            if (0 === strpos($originalPath, $storageBasePath)) {
                $path = substr($originalPath, strlen($storageBasePath));
            }

            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
            if ((bool) $queryBuilder
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
