<?php

namespace Vendor\EmailCollection\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Vendor\EmailCollection\Domain\Model\Subscriber;
use Vendor\EmailCollection\Domain\Repository\SubscriberRepository;

class SubscriberController extends ActionController
{
    // Cookie-Einstellungen
    private const COOKIE_NAME = 'email_collection_access';
    private const COOKIE_LIFETIME = 86400; // 1 Tag

    // Feste Weiterleitungs-ID für den Fall, dass die DB-Abfrage fehlschlägt
    private const FALLBACK_REDIRECT_PAGE_ID = 5; // Ändere dies zu deiner gewünschten Seiten-ID

    protected SubscriberRepository $subscriberRepository;

    /**
     * @var array<string, mixed>
     */
    protected array $contentObjectData = [];

    public function __construct(SubscriberRepository $subscriberRepository)
    {
        $this->subscriberRepository = $subscriberRepository;
    }

    public function initializeAction(): void
    {
        parent::initializeAction();

        // Get content object data
        if ($this->request->getAttribute('currentContentObject')) {
            $this->contentObjectData = $this->request->getAttribute('currentContentObject')->data;
        }
    }

    public function formAction(): ResponseInterface
    {
        $subscriber = GeneralUtility::makeInstance(Subscriber::class);
        $this->view->assign('subscriber', $subscriber);

        // Button anzeigen, falls E-Mail bereits gespeichert wurde
        $cookieExists = isset($_COOKIE[self::COOKIE_NAME]);
        $this->view->assign('showButton', $cookieExists);

        // Debug: Ausgabe Infos im Frontend
        $debug = [
            'Content-Element-UID' => $this->contentObjectData['uid'] ?? 'Nicht gefunden',
            'Pages-Feld' => $this->contentObjectData['pages'] ?? 'Nicht gesetzt',
            'ContentType' => $this->contentObjectData['CType'] ?? 'Unbekannt',
            'List-Type' => $this->contentObjectData['list_type'] ?? 'Nicht gesetzt',
        ];

        // Hole die Redirect-ID aus der Content-Element-Konfiguration - direkte SQL-Abfrage
        $contentElementUid = (int)($this->contentObjectData['uid'] ?? 0);
        $redirectPageId = $this->getRedirectPageIdDirectSQL($contentElementUid);

        $debug['Content-Element-UID für DB-Abfrage'] = $contentElementUid;
        $debug['Ermittelte Redirect-ID aus DB'] = $redirectPageId;

        // Weitere Infos aus der Datenbank - direkte SQL-Abfrage
        $debug['Rohdaten aus DB'] = $this->getRawContentElementDataDirectSQL($contentElementUid);

        $this->view->assign('debug', $debug);
        $this->view->assign('redirectPageId', $redirectPageId);

        return $this->htmlResponse();
    }

    public function saveAction(Subscriber $subscriber): ResponseInterface
    {
        // Direkt per SQL speichern - zuverlässigere Methode in TYPO3 v13
        $this->saveEmailDirectSQL($subscriber->getEmail());

        // Cookie setzen
        setcookie(
            self::COOKIE_NAME,
            md5($subscriber->getEmail()),
            time() + self::COOKIE_LIFETIME,
            '/'
        );

        // Hole die Redirect-ID aus der Content-Element-Konfiguration - direkte SQL-Abfrage
        $contentElementUid = (int)($this->contentObjectData['uid'] ?? 0);
        $redirectPageId = $this->getRedirectPageIdDirectSQL($contentElementUid);

        // Wenn eine Weiterleitungs-ID gefunden wurde, dorthin weiterleiten
        if ($redirectPageId > 0) {
            $url = $this->uriBuilder
                ->reset()
                ->setTargetPageUid($redirectPageId)
                ->setCreateAbsoluteUri(true)
                ->buildFrontendUri();

            // Direkter Redirect zur Zielseite
            header('Location: ' . $url);
            exit;
        }
        // Fallback: Feste ID verwenden, wenn keine in der DB gefunden wurde
        $url = $this->uriBuilder
            ->reset()
            ->setTargetPageUid(self::FALLBACK_REDIRECT_PAGE_ID)
            ->setCreateAbsoluteUri(true)
            ->buildFrontendUri();

        // Direkter Redirect zur Fallback-Zielseite
        header('Location: ' . $url);
        exit;
    }

    public function redirectAction(): ResponseInterface
    {
        $cookieExists = isset($_COOKIE[self::COOKIE_NAME]);
        $this->view->assign('cookieChecked', $cookieExists);
        return $this->htmlResponse();
    }

    /**
     * Holt die konfigurierte Weiterleitungs-ID mit direktem SQL
     */
    private function getRedirectPageIdDirectSQL(int $contentElementUid): int
    {
        if ($contentElementUid <= 0) {
            return 0;
        }

        try {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionByName('Default');

            $result = $connection->executeQuery(
                'SELECT tx_emailcollection_target_page FROM tt_content WHERE uid = ?',
                [$contentElementUid]
            )->fetchOne();

            return (int)$result;
        } catch (\Exception $e) {
            // Bei Fehler die Fallback-ID zurückgeben
            return self::FALLBACK_REDIRECT_PAGE_ID;
        }
    }

    /**
     * Debug-Funktion: Holt alle Daten eines Content-Elements mit direktem SQL
     *
     * @return array<string, mixed>
     */
    private function getRawContentElementDataDirectSQL(int $contentElementUid): array
    {
        if ($contentElementUid <= 0) {
            return ['error' => 'Ungültige Content-Element-UID'];
        }

        try {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionByName('Default');

            $result = $connection->executeQuery(
                'SELECT * FROM tt_content WHERE uid = ?',
                [$contentElementUid]
            )->fetchAssociative();

            return $result ?: ['error' => 'Content-Element nicht gefunden'];
        } catch (\Exception $e) {
            return ['error' => 'Datenbankfehler: ' . $e->getMessage()];
        }
    }

    /**
     * Speichert eine E-Mail direkt per SQL
     */
    private function saveEmailDirectSQL(string $email): void
    {
        try {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionByName('Default');

            // Storage PID aus dem Content-Element holen
            $storagePid = (int)($this->contentObjectData['pages'] ?? 0);

            // Wenn keine Storage PID gesetzt ist, nimm die aktuelle Seiten-ID
            if ($storagePid <= 0) {
                $storagePid = (int)($GLOBALS['TSFE']->id ?? 0);
            }

            $connection->insert(
                'tx_emailcollection_domain_model_subscriber',
                [
                    'pid' => $storagePid,
                    'email' => $email,
                    'crdate' => time(),
                    'tstamp' => time()
                ]
            );
        } catch (\Exception $e) {
            // Fehler beim Speichern protokollieren oder ignorieren
        }
    }
}
