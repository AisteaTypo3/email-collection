<?php

defined('TYPO3') or die();

// Plugin registrieren
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'EmailCollection',
    'Registration',
    'Email Collection: Registration'
);

// Zusätzliche Felder für Plugin-Konfiguration hinzufügen
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['emailcollection_registration'] = 'recursive,select_key';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['emailcollection_registration'] = 'tx_emailcollection_target_page';

// Neues Feld zur tt_content Tabelle hinzufügen
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_emailcollection_target_page' => [
        'exclude' => true,
        'label' => 'Weiterleitungsseite nach Registrierung',
        'config' => [
            'type' => 'input',
            'size' => 5,
            'eval' => 'int',
            'default' => 0
        ]
    ],
]);
