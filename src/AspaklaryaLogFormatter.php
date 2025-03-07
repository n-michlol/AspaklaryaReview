<?php

namespace MediaWiki\Extension\AspaklaryaReview;

use LogFormatter;
use MediaWiki\Linker\Linker;
use Message;

class AspaklaryaLogFormatter extends LogFormatter {
    
    protected function getMessageParameters() {
        $params = parent::getMessageParameters();
        
        if (isset($this->entry->getParameters()['filename'])) {
            $filename = $this->entry->getParameters()['filename'];
            $params[3] = $filename;
            
            if ($this->plaintext) {
                $params[4] = $filename;
            } else {
                $title = \Title::makeTitle(NS_FILE, $filename);
                $params[4] = \Message::rawParam(Linker::link($title));
            }
        }
        
        return $params;
    }
    
    public function getActionText() {
        $action = $this->entry->getSubtype();
        $params = $this->getMessageParameters();
        
        return $this->msg("logentry-aspaklaryareview-$action", $params)->text();
    }
}