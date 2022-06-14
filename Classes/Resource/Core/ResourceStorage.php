<?php

declare(strict_types=1);

namespace Plan2net\FakeFal\Resource\Core;

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;

/**
 * Class ResourceStorage
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class ResourceStorage extends \TYPO3\CMS\Core\Resource\ResourceStorage
{
    /**
     * Resets the isOnline flag for the storage (see parent constructor)
     * that is set to false there because of a missing directory
     * we create automatically for fake storages
     */
    public function __construct(DriverInterface $driver, array $storageRecord, EventDispatcherInterface $eventDispatcher = null)
    {
        parent::__construct($driver, $storageRecord, $eventDispatcher);

        if (false === $this->isOnline &&
            $this->storageRecord['tx_fakefal_enable'] &&
            $this->storageRecord['is_online']) {
            $this->isOnline = true;
        }
    }
}
