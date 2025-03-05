<?php

namespace MediaWiki\Extension\AspaklaryaReview;

use ExtensionRegistry;

class NotificationDefiner {
    public static function onBeforeCreateEchoEvent(&$notifications, &$categories, &$icons) {
        if (!ExtensionRegistry::getInstance()->isLoaded('Echo')) {
            wfLogWarning('Echo extension is not installed or loaded. Notifications will not be available.');
            return;
        }

        $icons['aspaklarya-approved'] = [
            'path' => 'Echo/modules/icons/check.svg',
        ];
        
        $icons['aspaklarya-edited'] = [
            'path' => 'Echo/modules/icons/edit.svg',
        ];
        
        $icons['aspaklarya-removed'] = [
            'path' => 'Echo/modules/icons/block.svg',
        ];
        
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
        
        wfDebugLog(
            'AspaklaryaReview', 
            'Registered notification types: ' . 
            implode(', ', array_keys($notifications))
        );
    }
}