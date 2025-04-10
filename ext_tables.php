<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

call_user_func(function () {
    // Plugin im "Plug-In" Dropdown sichtbar machen
    ExtensionManagementUtility::addPlugin(
        [
            'Email Collection: Registration', // Titel im Backend
            'emailcollection_registration',   // Plugin-Typ
            'EXT:email_collection/Resources/Public/Icons/user_plugin_registration.svg' // optionales Icon
        ],
        'list_type',
        'email_collection'
    );
});
