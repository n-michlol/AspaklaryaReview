<?php

namespace MediaWiki\Extension\AspaklaryaReview;

use ExtensionRegistry;

class NotificationDefiner {
    public static function onBeforeCreateEchoEvent(&$notifications, &$categories) {
        if (!ExtensionRegistry::getInstance()->isLoaded('Echo')) {
            wfLogWarning('Echo extension is not installed or loaded. Notifications will not be available.');
            return;
        }
        
        $categories['aspaklarya-review'] = [
            'priority' => 3,
            'tooltip' => 'echo-pref-tooltip-aspaklarya-review',
        ];

        $notifications['aspaklarya-approved'] = [
            'category' => 'aspaklarya-review',
            'group' => 'positive',
            'section' => 'alert',
            'canNotifyAgent' => true,
            'presentation-model' => 'MediaWiki\Extension\AspaklaryaReview\Notifications\ApprovedPresentationModel',
            'bundle' => [
                'web' => true,
                'email' => true,
                'expandable' => true
            ],
            'immediate' => true,
            'user-locators' => [
                [
                    'EchoUserLocator::locateFromEventExtra',
                    ['agent']
                ]
            ]
        ];

        $notifications['aspaklarya-removed'] = [
            'category' => 'aspaklarya-review',
            'group' => 'negative',
            'section' => 'alert',
            'canNotifyAgent' => true,
            'presentation-model' => 'MediaWiki\Extension\AspaklaryaReview\Notifications\RemovedPresentationModel',
            'bundle' => [
                'web' => true,
                'email' => true,
                'expandable' => true
            ],
            'immediate' => true,
            'user-locators' => [
                [
                    'EchoUserLocator::locateFromEventExtra',
                    ['agent']
                ]
            ]
        ];

        $notifications['aspaklarya-edited'] = [
            'category' => 'aspaklarya-review',
            'group' => 'positive',
            'section' => 'alert',
            'canNotifyAgent' => true,
            'presentation-model' => 'MediaWiki\Extension\AspaklaryaReview\Notifications\EditedPresentationModel',
            'bundle' => [
                'web' => true,
                'email' => true,
                'expandable' => true
            ],
            'immediate' => true,
            'user-locators' => [
                [
                    'EchoUserLocator::locateFromEventExtra',
                    ['agent']
                ]
            ]
        ];
    }
}