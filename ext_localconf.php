<?php

defined('TYPO3_MODE') or die('Access denied');

(static function () {
    if ((bool)\Plan2net\FakeFal\Utility\Configuration::getExtensionConfiguration('enable')) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\ResourceFactory::class] = [
            'className' => \Plan2net\FakeFal\Resource\Core\ResourceFactory::class
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\ResourceStorage::class] = [
            'className' => \Plan2net\FakeFal\Resource\Core\ResourceStorage::class
        ];
        // @todo after TYPO3 8 support dropped, use Symfony console commands
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] =
            \Plan2net\FakeFal\Command\SetupFakeStorage::class;
    }
})();
