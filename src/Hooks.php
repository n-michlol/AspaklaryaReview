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
        $title = $out->getTitle();
        
        if ($title->inNamespace(NS_MAIN) && $title->exists()) {
            $out->addModules(['ext.aspaklaryaReview']);
        }
        
        $user = $out->getUser();
        
        if ($user->isRegistered() && $this->permissionManager->userHasRight($user, 'aspaklarya-review')) {
            $out->addBodyClasses('aspaklarya-reviewer');
        }
    }

    public static function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater) {
        $updater->addExtensionTable(
            'aspaklarya_review_queue',
            __DIR__ . '/../sql/tables-generated.sql'
        );
    }

    public static function onExtensionTypes( array &$extTypes ) {
        $extTypes['aspaklarya'] = wfMessage( 'version-aspaklarya' )->text();
    }
}