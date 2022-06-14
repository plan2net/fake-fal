<?php

defined('TYPO3_MODE') || exit('Access denied.');

$fields = [
    'tx_fakefal_enable' => [
        'label' => 'LLL:EXT:fake_fal/Resources/Private/Language/locallang_db.xlf:sys_file_storage.tx_fakefal_enable',
        'displayCond' => 'FIELD:driver:=:Local',
        'config' => [
            'type' => 'check',
            'default' => 0
        ]
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_storage', $fields);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_storage',
    'tx_fakefal_enable',
    '',
    'after:is_default'
);
