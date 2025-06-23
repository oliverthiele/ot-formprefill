<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Form Prefill for FE Users',
    'description' => 'Automatically prefill form fields for logged-in frontend users using data from fe_users. Supports flexible field mapping via FlexForm and works with any TYPO3 form setup.',
    'category' => 'plugin',
    'author' => 'Oliver Thiele',
    'author_email' => 'mail@oliver-thiele.de',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.14-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
