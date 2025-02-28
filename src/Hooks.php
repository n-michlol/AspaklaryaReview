<?php

namespace MediaWiki\Extension\AspaklaryaReview;

use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserFactory;
use OutputPage;
use Skin;
use DatabaseUpdater;

class Hooks implements BeforePageDisplayHook {
    private $permissionManager;
    private $userFactory;

    public function __construct(
        PermissionManager $permissionManager,
        UserFactory $userFactory
    ) {
        $this->permissionManager = $permissionManager;
        $this->userFactory = $userFactory;
    }

    public function onBeforePageDisplay($out, $skin): void {
        $user = $out->getUser();
        
        /*
        Add the module only for logged-in users - I thought it was better that way.
        If there is a different decision, it will require thinking about what to do with 
        the messages to the user who sent the image for review.
        */
        if ($user->isRegistered()) {
            $out->addModules(['ext.aspaklaryaReview']);
        }
    }

    /**
     * Static method for schema updates to avoid DI issues
     *
     * @param DatabaseUpdater $updater
     * @return void
     */
    public static function onLoadExtensionSchemaUpdates($updater): void {
        $dbType = $updater->getDB()->getType();
        
        if ($dbType === 'mysql' || $dbType === 'sqlite') {
            $updater->addExtensionTable(
                'aspaklarya_review_queue',
                __DIR__ . '/../sql/tables-generated.sql'
            );
        }
    }
}