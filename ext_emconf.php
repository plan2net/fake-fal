<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Local FAL driver for fake files',
    'description' => 'Creates missing files on demand for development',
    'category' => 'be',
    'author' => 'Wolfgang Klinger & others',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'author_company' => 'plan2net GmbH',
    'version' => '2.3.1',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.5.99'
        ],
        'conflicts' => [
            'filefill' => ''
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ]
];
