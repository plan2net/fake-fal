<?php

defined('TYPO3_MODE') or die('Access denied');

if (version_compare(TYPO3_version, '8.7', '<') === true) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers']['LocalFake'] = [
        'class' => 'Plan2net\FakeFal\Resource\Driver\LocalDriver67',
        'shortName' => 'LocalFake',
        'flexFormDS' => 'FILE:EXT:core/Configuration/Resource/Driver/LocalDriverFlexForm.xml',
        'label' => 'Local fake filesystem'
    ];
}
else {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers']['LocalFake'] = [
        'class' => 'Plan2net\FakeFal\Resource\Driver\LocalDriver',
        'shortName' => 'LocalFake',
        'flexFormDS' => 'FILE:EXT:core/Configuration/Resource/Driver/LocalDriverFlexForm.xml',
        'label' => 'Local fake filesystem'
    ];
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\CMS\Core\Resource\Processing\LocalCropScaleMaskHelper'] = [
    'className' => 'Plan2net\FakeFal\Resource\Processing\LocalCropScaleMaskHelper'
];

if (version_compare(TYPO3_version, '8.7', '<') === true) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'Plan2net\FakeFal\Command\FakeStorage67CommandController';
}
else {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'Plan2net\FakeFal\Command\FakeStorageCommandController';
}
