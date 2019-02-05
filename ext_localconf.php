<?php

defined('TYPO3_MODE') or die('Access denied');

(function() {
    // Currently not using the API, as it's different between 8 and 9
    $extensionConfiguration = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fake_fal']);
    if ((bool)$extensionConfiguration['enable']) {
        /** @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher $dispatcher */
        $dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
        $dispatcher->connect(
            \TYPO3\CMS\Core\Resource\ResourceFactory::class,
            \TYPO3\CMS\Core\Resource\ResourceFactoryInterface::SIGNAL_PostProcessStorage,
            \Plan2net\FakeFal\Resource\Slot\ResourceFactorySlot::class,
            'initializeResourceStorage'
        );
    }
})();
