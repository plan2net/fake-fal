<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Local FAL driver to create missing files',
    'description' => 'Creates missing files (images) on demand for development and testing',
    'category' => 'be',
    'author' => 'Wolfgang Klinger, Ioulia Kondratovitch, Martin Kutschker',
    'author_email' => 'wk@plan2.net',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'author_company' => 'plan2net GmbH',
    'version' => '2.4.1',
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
