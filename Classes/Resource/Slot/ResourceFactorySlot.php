<?php
declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Slot;

use Plan2net\FakeFal\Resource\Driver\LocalFakeDriver;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ResourceFactorySlot
 *
 * @package Plan2net\FakeFal\Resource\Slot
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class ResourceFactorySlot
{
    /**
     * Replaces the current driver for the storage
     * if the original driver type is 'local' and
     * the fake driver is enabled in storage configuration
     *
     * @param ResourceFactory $resourceFactory
     * @param ResourceStorage $resourceStorage
     */
    public function initializeResourceStorage(
        ResourceFactory $resourceFactory,
        ResourceStorage $resourceStorage
    ) {
        $storageRecord = $resourceStorage->getStorageRecord();
        // Virtual default storage
        if ($storageRecord['uid'] === 0) {
            $configuration = $resourceStorage->getConfiguration();
            // Default configuration
            $configuration += [
                'basePath' => '/',
                'pathType' => 'relative'
            ];
            $resourceStorage->setConfiguration($configuration);
            $resourceStorage->setDriver($this->getFakeDriver($resourceStorage));
        } else {
            $isLocalDriver = $storageRecord['driver'] === 'Local';
            $isFakeDriverEnabled = !empty($storageRecord['tx_fakefal_enable']);
            if ($isLocalDriver && $isFakeDriverEnabled) {
                $resourceStorage->setDriver($this->getFakeDriver($resourceStorage));
            }
        }
    }

    /**
     * @param ResourceStorage $resourceStorage
     * @return LocalFakeDriver
     */
    protected function getFakeDriver(ResourceStorage $resourceStorage): LocalFakeDriver
    {
        $storageRecord = $resourceStorage->getStorageRecord();
        /** @var LocalFakeDriver $driver */
        $driver = GeneralUtility::makeInstance(LocalFakeDriver::class, $resourceStorage->getConfiguration());
        $driver->setStorageUid($storageRecord['uid']);
        $driver->mergeConfigurationCapabilities($resourceStorage->getCapabilities());
        $driver->processConfiguration();
        $driver->initialize();

        return $driver;
    }
}
