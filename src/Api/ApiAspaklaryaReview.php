<?php

namespace MediaWiki\Extension\AspaklaryaReview\Api;

use ApiBase;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILoadBalancer;
use WikiPage;
use Title;
use CommentStoreComment;
use WikitextContent;

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
        
        $dbw = $this->loadBalancer->getConnection(DB_PRIMARY);
        
        switch ($params['do']) {
            case 'submit':
                // Add to review queue
                $dbw->insert(
                    'aspaklarya_review_queue',
                    [
                        'arq_filename' => $params['filename'],
                        'arq_page_id' => $params['pageid'],
                        'arq_requester' => $user->getId(),
                        'arq_timestamp' => $dbw->timestamp()
                    ],
                    __METHOD__
                );
                break;
                
            case 'remove':
                if (!$user->isAllowed('aspaklarya-review')) {
                    $this->dieWithError('permissiondenied');
                }
                
                $row = $dbw->selectRow(
                    'aspaklarya_review_queue',
                    '*',
                    ['arq_id' => $params['id']]
                );
                
                if ($row) {
                    // Update status
                    $dbw->update(
                        'aspaklarya_review_queue',
                        [
                            'arq_status' => 'removed',
                            'arq_reviewer' => $user->getId(),
                            'arq_review_timestamp' => $dbw->timestamp()
                        ],
                        ['arq_id' => $params['id']]
                    );
                    
                    $this->removeImage($row->arq_filename);
                    
                    // Send notification
                    $notificationId = $this->sendNotification(
                        $row->arq_requester,
                        'removed',
                        $row->arq_filename
                    );
                    
                    $this->getResult()->addValue(null, 'notification', $notificationId);
                }
                break;
                
            case 'approve':
            case 'edited':
                if (!$user->isAllowed('aspaklarya-review')) {
                    $this->dieWithError('permissiondenied');
                }
                
                $row = $dbw->selectRow(
                    'aspaklarya_review_queue',
                    '*',
                    ['arq_id' => $params['id']]
                );
                
                if ($row) {
                    $dbw->update(
                        'aspaklarya_review_queue',
                        [
                            'arq_status' => $params['do'],
                            'arq_reviewer' => $user->getId(),
                            'arq_review_timestamp' => $dbw->timestamp()
                        ],
                        ['arq_id' => $params['id']]
                    );
                    
                    $notificationId = $this->sendNotification(
                        $row->arq_requester,
                        $params['do'],
                        $row->arq_filename
                    );
                    
                    $this->getResult()->addValue(null, 'notification', $notificationId);
                }
                break;
        }
        
        $this->getResult()->addValue(null, 'success', true);
    }

    private function sendNotification($userId, $type, $filename) {
        $notificationManager = \MediaWiki\MediaWikiServices::getInstance()
            ->getService('EchoNotificationManager');
        
        $extra = [
            'filename' => $filename,
            'reviewer' => $this->getUser()->getName()
        ];
        
        $notification = $notificationManager->createNotification(
            $userId,
            'aspaklarya-' . $type,
            $extra
        );
        
        return $notification->getId();
    }

    private function removeImage($filename) {
        $services = \MediaWiki\MediaWikiServices::getInstance();
        $dbr = $this->loadBalancer->getConnection(DB_REPLICA);
        
        // Get all pages that use this file
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
            
            $page = WikiPage::factory($title);
            $content = $page->getContent();
            
            if (!$content) {
                continue;
            }
            
            $text = $content->getText();
            
            $text = $this->removeImageFromText($text, $filename); // Remove image using regex patterns
            
            $updater = $page->newPageUpdater($this->getUser());
            $updater->setContent('text', new WikitextContent($text));
            $updater->saveRevision(
                CommentStoreComment::newUnsavedComment('הסרת תמונה'),
                EDIT_MINOR | EDIT_FORCE_BOT
            );
        }
        
        // Create redirect page if needed
        $fileTitle = Title::makeTitle(NS_FILE, $filename);
        if (!$fileTitle->exists()) {
            $page = WikiPage::factory($fileTitle);
            $updater = $page->newPageUpdater($this->getUser());
            $updater->setContent('text', new WikitextContent('#הפניה [[קובץ:תמונה חילופית.jpg]]'));
            $updater->saveRevision(
                CommentStoreComment::newUnsavedComment('חסימת תמונה'),
                EDIT_MINOR | EDIT_FORCE_BOT
            );
        }
    }

    private function removeImageFromText($text, $filename) {
        $filename = preg_quote($filename, '/');
        $filename = str_replace('\\s', '[_\\s]', $filename);
        
        // Gallery pattern
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
                ApiBase::PARAM_TYPE => ['submit', 'remove', 'approve', 'edited'],
                ApiBase::PARAM_REQUIRED => true
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

    public function getTokenSalt() {
        return '';
    }

    protected function getExamplesMessages() {
        return [
            'action=aspaklaryareview&do=submit&filename=Example.jpg&pageid=123'
                => 'apihelp-aspaklaryareview-example-submit',
            'action=aspaklaryareview&do=remove&id=456'
                => 'apihelp-aspaklaryareview-example-remove'
        ];
    }
}
