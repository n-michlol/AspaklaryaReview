<?php

namespace MediaWiki\Extension\AspaklaryaReview\Notifications;

use EchoEventPresentationModel;
use Message;

class RemovedPresentationModel extends EchoEventPresentationModel {
    public function getIconType() {
        return 'trash';
    }

    public function getHeaderMessage() {
        $filename = $this->event->getExtraParam('filename');
        return new Message('aspaklarya-notification-removed', [$filename]);
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
}