<?php

declare(strict_types=1);

namespace Plan2net\FakeFal\EventListener;

use Plan2net\FakeFal\Resource\Driver\LocalFakeDriver;
use TYPO3\CMS\Core\Resource\Event\AfterResourceStorageInitializationEvent;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ResourceStorageInitializationEventListener
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
final class ResourceStorageInitializationEventListener
{
    public function __invoke(AfterResourceStorageInitializationEvent $event): void
    {
        $storage = $event->getStorage();
        $storageRecord = $storage->getStorageRecord();
        if (0 === $storageRecord['uid']) {
            $configuration = $storage->getConfiguration();
            // Default configuration
            $configuration += [
                'basePath' => '/',
                'pathType' => 'relative'
            ];
            $storage->setConfiguration($configuration);
            $storage->setDriver($this->getFakeDriver($storage));
        } else {
            $isLocalDriver = 'Local' === $storageRecord['driver'];
            $isFakeDriverEnabled = !empty($storageRecord['tx_fakefal_enable']);
            if ($isLocalDriver && $isFakeDriverEnabled) {
                $storage->setDriver($this->getFakeDriver($storage));
            }
        }
    }

    private function getFakeDriver(ResourceStorage $resourceStorage): LocalFakeDriver
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
