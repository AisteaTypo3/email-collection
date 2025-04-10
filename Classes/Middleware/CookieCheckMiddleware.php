<?php

namespace Vendor\EmailCollection\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class CookieCheckMiddleware implements MiddlewareInterface
{
    protected Context $context;
    protected ExtensionConfiguration $extensionConfiguration;
    protected SiteFinder $siteFinder;
    protected TypoScriptService $typoScriptService;

    public function __construct(
        Context $context,
        ExtensionConfiguration $extensionConfiguration,
        SiteFinder $siteFinder,
        TypoScriptService $typoScriptService
    ) {
        $this->context = $context;
        $this->extensionConfiguration = $extensionConfiguration;
        $this->siteFinder = $siteFinder;
        $this->typoScriptService = $typoScriptService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Only process pages with the marker in TypoScript
        $pageId = (int)($request->getQueryParams()['id'] ?? 0);

        // Skip if not on a protected page
        if (!$this->isProtectedPage($pageId)) {
            return $handler->handle($request);
        }

        // Get configuration from TypoScript settings
        $tsSettings = $this->getTypoScriptSettings();
        $cookieName = $tsSettings['cookieName'] ?? 'email_collection_access';

        // Check if we have a registration page configured
        $registrationPageUid = (int)($tsSettings['registrationPageUid'] ?? 0);
        if ($registrationPageUid <= 0) {
            // Try to get from extension configuration fallback
            try {
                $extConfig = $this->extensionConfiguration->get('email_collection');
                $registrationPageUid = (int)($extConfig['registrationPageUid'] ?? 0);
            } catch (\Exception $e) {
                $registrationPageUid = 0;
            }
        }

        // Check for cookie
        $cookies = $request->getCookieParams();

        if (!isset($cookies[$cookieName])) {
            // Redirect to registration page if cookie is not set and we have a registration page
            if ($registrationPageUid > 0) {
                $uri = $this->buildUri($registrationPageUid);
                return new RedirectResponse($uri, 302);
            }

            // Set a TSFE variable to indicate cookie status
            if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
                $GLOBALS['TSFE']->register['cookieChecked'] = 0;
            }
        } else {
            // Cookie found, access granted
            if (isset($GLOBALS['TSFE']) && $GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
                $GLOBALS['TSFE']->register['cookieChecked'] = 1;
            }
        }

        return $handler->handle($request);
    }

    protected function isProtectedPage(int $pageId): bool
    {
        if ($pageId <= 0) {
            return false;
        }

        // Get list of protected pages from TypoScript
        $tsSettings = $this->getTypoScriptSettings();
        $protectedPagesString = $tsSettings['protectedPages'] ?? '';
        $protectedPages = GeneralUtility::intExplode(',', $protectedPagesString, true);

        return in_array($pageId, $protectedPages);
    }

    protected function buildUri(int $pageUid): string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            return (string)$site->getRouter()->generateUri($pageUid);
        } catch (\Exception $e) {
            return '/';
        }
    }

    /**
     * @return array{
     *     cookieName?: string,
     *     protectedPages?: string,
     *     registrationPageUid?: int
     * }
     */
    protected function getTypoScriptSettings(): array
    {
        try {
            /** @var array{cookieName?: string, protectedPages?: string, registrationPageUid?: int} $extConfig */
            $extConfig = $this->extensionConfiguration->get('email_collection');
            return $extConfig;
        } catch (\Exception $e) {
            return [
                'cookieName' => 'email_collection_access',
                'protectedPages' => '',
                'registrationPageUid' => 0
            ];
        }
    }
}
