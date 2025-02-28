<?php

namespace MediaWiki\Extension\AspaklaryaReview\Notifications;

use EchoEventPresentationModel;
use Message;

class ApprovedPresentationModel extends EchoEventPresentationModel {
    public function getIconType() {
        return 'check';
    }

    public function getHeaderMessage() {
        $filename = $this->event->getExtraParam('filename');
        return new Message('aspaklarya-notification-approved', [$filename]);
    }

    public function getBodyMessage() {
        return false;
    }

    public function getPrimaryLink() {
        $filename = $this->event->getExtraParam('filename');
        return [
            'url' => \Title::newFromText($filename, NS_FILE)->getFullURL(),
            'label' => $this->msg('notification-link-text-view-file')->text()
        ];
    }
    
    public function getSecondaryLinks() {
        return [];
    }
}