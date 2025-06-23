<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$customCType = 'formprefill';

$GLOBALS['TCA']['tt_content']['types'][$customCType] = [
    'showitem' => '
        --palette--;;general,
        header, pi_flexform,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
        --palette--;;hidden,
        --palette--;;access,
    ',
];

ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'CType',
    [
        'LLL:EXT:ot_formprefill/Resources/Private/Language/de.locallang_db.xlf:CType',
        $customCType,
        'ot-formprefill',
        'forms',
        'LLL:EXT:ot_formprefill/Resources/Private/Language/de.locallang_db.xlf:CType.description',
    ],
    'form_formframework',
    'after'
);

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:ot_formprefill/Configuration/FlexForms/FormPrefill.xml',
    $customCType
);
