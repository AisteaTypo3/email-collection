<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Vendor\EmailCollection\Controller\SubscriberController; // WICHTIG: Namespace hinzufügen

(function () {
    ExtensionUtility::configurePlugin(
        'EmailCollection',
        'Registration',
        [
            SubscriberController::class => 'form, save, redirect'
        ],
        [
            SubscriberController::class => 'form, save, redirect'
        ]
    );

    // TypoScript einbinden
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
        '@import "EXT:email_collection/Configuration/TypoScript/setup.typoscript"'
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptConstants(
        '@import "EXT:email_collection/Configuration/TypoScript/constants.typoscript"'
    );
})();

call_user_func(function () {
    $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
    $iconRegistry->registerIcon(
        'emailcollection-registration', // Eindeutiger Identifier
        SvgIconProvider::class,
        ['source' => 'EXT:email_collection/Resources/Public/Icons/user_plugin_registration.svg']
    );
});
