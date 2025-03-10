<?php

namespace MediaWiki\Extension\AspaklaryaReview\Api;

use ApiBase;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILoadBalancer;
use Title;
use CommentStoreComment;
use WikitextContent;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;
use ExtensionRegistry;
use ManualLogEntry;

class ApiAspaklaryaReview extends ApiBase {
    private $loadBalancer;
    private $userFactory;

    public function __construct(
        $query,
        $moduleName,
        ILoadBalancer $loadBalancer,
        UserFactory $userFactory
    ) {
        parent::__construct($query, $moduleName);
        $this->loadBalancer = $loadBalancer;
        $this->userFactory = $userFactory;
    }

    public function execute() {
        $user = $this->getUser();
        $params = $this->extractRequestParams();
        
        if (!$user->isRegistered()) {
            $this->dieWithError('You must be logged in to use this feature', 'notloggedin');
        }
        
        $dbw = $this->loadBalancer->getConnection(DB_PRIMARY);
        
        $action = $params['do'] ?? 'submit';
        
        try {
            switch ($action) {
                case 'checkprevious':
                    if (empty($params['filename'])) {
                        $this->dieWithError('Missing filename parameter', 'missingparam');
                    }

                    wfDebug("AspaklaryaReview checking previous review for filename: " . $params['filename']);

                    $row = $dbw->selectRow(
                        'aspaklarya_review_queue',
                        '*',
                        [
                            'arq_filename' => $params['filename'],
                            'arq_status' => ['approved', 'removed', 'edited']
                        ],
                        __METHOD__,
                        [
                            'ORDER BY' => 'arq_review_timestamp DESC'
                        ]
                    );

                    wfDebug("AspaklaryaReview previous review result: " . ($row ? 'found' : 'not found'));
                
                    if ($row) {
                        $reviewerName = '';
                        try {
                            $reviewer = $this->userFactory->newFromId($row->arq_reviewer);
                            $reviewerName = $reviewer ? $reviewer->getName() : '(unknown)';
                        } catch (\Exception $e) {
                            $reviewerName = '(error)';
                            wfLogWarning('Error getting reviewer name: ' . $e->getMessage());
                        }
                
                        $this->getResult()->addValue(null, 'previousReview', [
                            'status' => $row->arq_status,
                            'timestamp' => wfTimestamp(TS_ISO_8601, $row->arq_review_timestamp),
                            'reviewer' => $reviewerName
                        ]);
                    } else {
                        $this->getResult()->addValue(null, 'previousReview', false);
                    }
                    break;
                    
                case 'submit':
                    if (!isset($params['filename']) || !isset($params['pageid'])) {
                        $this->dieWithError('Missing required parameters', 'missingparam');
                    }
                    
                    $exists = $dbw->selectRow(
                        'aspaklarya_review_queue',
                        'arq_id',
                        [
                            'arq_filename' => $params['filename'],
                            'arq_page_id' => (int)$params['pageid'],
                            'arq_status' => 'pending'
                        ],
                        __METHOD__
                    );
                    
                    if ($exists) {
                        $this->getResult()->addValue(null, 'success', true);
                        return;
                    }
                    
                    $dbw->insert(
                        'aspaklarya_review_queue',
                        [
                            'arq_filename' => $params['filename'],
                            'arq_page_id' => (int)$params['pageid'],
                            'arq_requester' => $user->getId(),
                            'arq_timestamp' => $dbw->timestamp(),
                            'arq_status' => 'pending'
                        ],
                        __METHOD__
                    );
                    
                    if (!$dbw->affectedRows()) {
                        $this->dieWithError('Failed to insert record', 'insertfailed');
                    }
                    
                    $this->getResult()->addValue(null, 'success', true);
                    $this->addLogEntry('submit', $user->getId(), (int)$params['pageid'], $params['filename']);
                    break;
                    
                case 'remove':
                    if (!$user->isAllowed('aspaklarya-review')) {
                        $this->dieWithError('You do not have permission to review images', 'permissiondenied');
                    }
                    
                    if (!isset($params['id'])) {
                        $this->dieWithError('Missing ID parameter', 'missingparam');
                    }
                    
                    $row = $dbw->selectRow(
                        'aspaklarya_review_queue',
                        '*',
                        ['arq_id' => (int)$params['id']],
                        __METHOD__
                    );
                    
                    if (!$row) {
                        $this->dieWithError('Record not found', 'notfound');
                    }
                    
                    $dbw->update(
                        'aspaklarya_review_queue',
                        [
                            'arq_status' => 'removed',
                            'arq_reviewer' => $user->getId(),
                            'arq_review_timestamp' => $dbw->timestamp()
                        ],
                        ['arq_id' => (int)$params['id']],
                        __METHOD__
                    );
                    
                    $this->removeImage($row->arq_filename);
                    
                    $notificationId = $this->sendNotification(
                        $row->arq_requester,
                        'removed',
                        $row->arq_filename
                    );
                    
                    if ($notificationId) {
                        $this->getResult()->addValue(null, 'notification', $notificationId);
                    }
                    
                    $this->getResult()->addValue(null, 'success', true);
                    $this->addLogEntry('removed', $user->getId(), $row->arq_page_id, $row->arq_filename);
                    break;
                    
                case 'approve':
                    if (!$user->isAllowed('aspaklarya-review')) {
                        $this->dieWithError('You do not have permission to review images', 'permissiondenied');
                    }
                    
                    if (!isset($params['id'])) {
                        $this->dieWithError('Missing ID parameter', 'missingparam');
                    }
                    
                    $row = $dbw->selectRow(
                        'aspaklarya_review_queue',
                        '*',
                        ['arq_id' => (int)$params['id']],
                        __METHOD__
                    );
                    
                    if (!$row) {
                        $this->dieWithError('Record not found', 'notfound');
                    }
                    
                    $dbw->update(
                        'aspaklarya_review_queue',
                        [
                            'arq_status' => 'approved',
                            'arq_reviewer' => $user->getId(),
                            'arq_review_timestamp' => $dbw->timestamp()
                        ],
                        ['arq_id' => (int)$params['id']],
                        __METHOD__
                    );
                    
                    $notificationId = $this->sendNotification(
                        $row->arq_requester,
                        'approved',
                        $row->arq_filename
                    );
                    
                    if ($notificationId) {
                        $this->getResult()->addValue(null, 'notification', $notificationId);
                    }
                    
                    $this->getResult()->addValue(null, 'success', true);
                    $this->addLogEntry('approved', $user->getId(), $row->arq_page_id, $row->arq_filename);
                    break;
                    
                case 'edited':
                    if (!$user->isAllowed('aspaklarya-review')) {
                        $this->dieWithError('You do not have permission to review images', 'permissiondenied');
                    }
                    
                    if (!isset($params['id'])) {
                        $this->dieWithError('Missing ID parameter', 'missingparam');
                    }
                    
                    $row = $dbw->selectRow(
                        'aspaklarya_review_queue',
                        '*',
                        ['arq_id' => (int)$params['id']],
                        __METHOD__
                    );
                    
                    if (!$row) {
                        $this->dieWithError('Record not found', 'notfound');
                    }
                    
                    $dbw->update(
                        'aspaklarya_review_queue',
                        [
                            'arq_status' => 'edited',
                            'arq_reviewer' => $user->getId(),
                            'arq_review_timestamp' => $dbw->timestamp()
                        ],
                        ['arq_id' => (int)$params['id']],
                        __METHOD__
                    );
                    
                    $notificationId = $this->sendNotification(
                        $row->arq_requester,
                        'edited',
                        $row->arq_filename
                    );
                    
                    if ($notificationId) {
                        $this->getResult()->addValue(null, 'notification', $notificationId);
                    }
                    
                    $this->getResult()->addValue(null, 'success', true);
                    $this->addLogEntry('edited', $user->getId(), $row->arq_page_id, $row->arq_filename);
                    break;
                    
                default:
                    $this->dieWithError('Invalid action', 'invalidaction');
            }
        } catch (\Exception $e) {
            wfLogWarning('AspaklaryaReview API error: ' . $e->getMessage());
            $this->dieWithError('Error: ' . $e->getMessage(), 'internalerror');
        }
    }

    private function sendNotification($userId, $type, $filename) {
        if (!ExtensionRegistry::getInstance()->isLoaded('Echo')) {
            wfLogWarning('Echo extension is not loaded. Cannot send notification.');
            return null;
        }

        try {
            $services = MediaWikiServices::getInstance();
            $targetUser = $this->userFactory->newFromId($userId);
            
            if (!$targetUser || !$targetUser->isRegistered()) {
                wfLogWarning('Cannot send notification: target user not found or not registered.');
                return null;
            }

            $notificationType = 'aspaklarya-' . $type;
            
            if (!in_array($notificationType, ['aspaklarya-approved', 'aspaklarya-removed', 'aspaklarya-edited'])) {
                wfLogWarning("Invalid notification type: $notificationType");
                return null;
            }
            
            $extra = [
                'filename' => $filename,
                'reviewer' => $this->getUser()->getName(),
                'agent' => $userId
            ];
            
            if (!class_exists('\EchoEvent')) {
                wfLogWarning('EchoEvent class not found. Cannot send notification.');
                return null;
            }
            
            $event = \EchoEvent::create([
                'type' => $notificationType,
                'agent' => $this->getUser(),
                'extra' => $extra
            ]);
            
            if ($event) {
                return $event->getId();
            } else {
                wfLogWarning("Failed to create Echo event of type $notificationType");
                return null;
            }
        } catch (\Exception $e) {
            wfLogWarning('Failed to send notification: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return null;
        }
    }

    private function removeImage($filename) {
        try {
            $services = MediaWikiServices::getInstance();
            $dbr = $this->loadBalancer->getConnection(DB_REPLICA);
            
            $res = $dbr->select(
                'imagelinks',
                'il_from',
                ['il_to' => str_replace(' ', '_', $filename)],
                __METHOD__
            );
            
            foreach ($res as $row) {
                $title = Title::newFromID($row->il_from);
                if (!$title) {
                    continue;
                }
                
                $wikiPageFactory = $services->getWikiPageFactory();
                $page = $wikiPageFactory->newFromTitle($title);
                $content = $page->getContent();
                
                if (!$content) {
                    continue;
                }
                
                $text = $content->getText();
                
                $text = $this->removeImageFromText($text, $filename);
                
                $updater = $page->newPageUpdater($this->getUser());
                $updater->setContent(SlotRecord::MAIN, new WikitextContent($text)); 
                $updater->saveRevision(
                    CommentStoreComment::newUnsavedComment('הסרת תמונה'),
                    EDIT_MINOR | EDIT_FORCE_BOT
                );
            }
            
            $fileTitle = Title::makeTitle(NS_FILE, $filename);
            if ($fileTitle->exists()) {
                $wikiPageFactory = $services->getWikiPageFactory();
                $page = $wikiPageFactory->newFromTitle($fileTitle);
                $updater = $page->newPageUpdater($this->getUser());
                $updater->setContent(SlotRecord::MAIN, new WikitextContent('#הפניה [[קובץ:תמונה חילופית.jpg]]'));
                $updater->saveRevision(
                    CommentStoreComment::newUnsavedComment('חסימת תמונה'),
                    EDIT_MINOR | EDIT_FORCE_BOT
                );
            }
        } catch (\Exception $e) {
            wfLogWarning('Error removing image: ' . $e->getMessage());
        }
    }

    private function removeImageFromText($text, $filename) {
        $filename = preg_quote($filename, '/');
        $filename = str_replace('\\s', '[_\\s]', $filename);
        
        $text = preg_replace(
            '/<gallery([^>]*)>\s*' . $filename . '\s*(\|[^\n]*\n|\n)/is',
            '<gallery$1>',
            $text
        );
        
        $text = preg_replace('/<gallery[^>]*>\s*<\/gallery>/is', '', $text);
        
        $patterns = [
            '/\[\[\s*:?\s*(Image|image|תמונה|קו|קובץ|file|File)\s*:\s*' . $filename . '[^\[\]]*\]\]/i',
            '/\[\[(Image|image|תמונה|קו|קובץ|file|File)\s*:\s*' . $filename . '\s*\|.*?\]\]/i'
        ];
        
        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        
        return $text;
    }

    public function getAllowedParams() {
        return [
            'do' => [
                ApiBase::PARAM_TYPE => ['submit', 'remove', 'approve', 'edited', 'checkprevious'],
                ApiBase::PARAM_REQUIRED => false,
                ParamValidator::PARAM_DEFAULT => 'submit'
            ],
            'id' => [
                ApiBase::PARAM_TYPE => 'integer',
                ApiBase::PARAM_REQUIRED => false
            ],
            'filename' => [
                ApiBase::PARAM_TYPE => 'string',
                ApiBase::PARAM_REQUIRED => false
            ],
            'pageid' => [
                ApiBase::PARAM_TYPE => 'integer',
                ApiBase::PARAM_REQUIRED => false
            ]
        ];
    }

    public function needsToken() {
        return 'csrf';
    }

    public function isWriteMode() {
        return true;
    }

    protected function getExamplesMessages() {
        return [
            'action=aspaklaryareview&do=submit&filename=Example.jpg&pageid=123'
                => 'apihelp-aspaklaryareview-example-submit',
            'action=aspaklaryareview&do=remove&id=456'
                => 'apihelp-aspaklaryareview-example-remove',
            'action=aspaklaryareview&do=checkprevious&filename=Example.jpg'
                => 'apihelp-aspaklaryareview-example-checkprevious'
        ];
    }

    private function addLogEntry($action, $userId, $pageId, $filename) {
        try {
            $logEntry = new ManualLogEntry('aspaklaryareview', $action);
            $logEntry->setPerformer($this->getUser());
            $title = Title::newFromID($pageId);
            if ($title) {
                $logEntry->setTarget($title);
            } else {
                $fileTitle = Title::makeTitle(NS_FILE, $filename);
                $logEntry->setTarget($fileTitle);
            }
            
            $logEntry->setParameters([
                'filename' => $filename,
                '4::filename' => $filename
            ]);
            
            $logId = $logEntry->insert();
            $logEntry->publish($logId);
            return $logId;
        } catch (\Exception $e) {
            wfLogWarning('Failed to add log entry: ' . $e->getMessage());
            return false;
        }
    }
}