<?php

defined('TYPO3') or die();

// Define the extension key
$_EXTKEY = 'email_collection';

$EM_CONF[$_EXTKEY] = [
    'title' => 'Email Collection',
    'description' => 'Simple email collection with page access control',
    'category' => 'plugin',
    'author' => 'Yannick Aister',
    'author_email' => 'yannick.aister@medartis.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
