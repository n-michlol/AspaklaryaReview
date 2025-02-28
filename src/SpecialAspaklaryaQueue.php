<?php

namespace MediaWiki\Extension\AspaklaryaReview;

use SpecialPage;
use HTMLForm;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILoadBalancer;
use Status;
use OOUI;
use Html;

class SpecialAspaklaryaQueue extends SpecialPage {
    private $loadBalancer;
    private $userFactory;

    public function __construct(
        ILoadBalancer $loadBalancer,
        UserFactory $userFactory
    ) {
        parent::__construct('AspaklaryaQueue', 'aspaklarya-review');
        $this->loadBalancer = $loadBalancer;
        $this->userFactory = $userFactory;
    }

    public function execute($par) {
        $this->checkPermissions();
        $this->setHeaders();
        $out = $this->getOutput();

        $out->enableOOUI();
        $out->addModules(['ext.aspaklaryaQueue', 'oojs-ui-core', 'oojs-ui-widgets']);
        $out->setPageTitle($this->msg('aspaklarya-queue-title'));

        $dbr = $this->loadBalancer->getConnection(DB_REPLICA);
        
        try {
            $res = $dbr->select(
                'aspaklarya_review_queue',
                '*',
                ['arq_status' => 'pending'],
                __METHOD__,
                ['ORDER BY' => 'arq_timestamp DESC']
            );

            if ($res->numRows() === 0) {
                $out->addWikiMsg('aspaklarya-queue-empty');
                return;
            }

            $html = '<div class="aspaklarya-queue-list">';
            
            foreach ($res as $row) {
                $requester = $this->userFactory->newFromId($row->arq_requester);
                $filename = $row->arq_filename;
                
                $html .= $this->formatQueueItem(
                    $row->arq_id,
                    $filename,
                    $requester->getName(),
                    $row->arq_timestamp,
                    $row->arq_page_id
                );
            }
            
            $html .= '</div>';
            $out->addHTML($html);
        } catch (\Exception $e) {
            $out->addHTML(Html::errorBox('Error loading review queue: ' . $e->getMessage()));
        }
    }

    private function formatQueueItem($id, $filename, $requester, $timestamp, $pageId) {
        $title = \Title::newFromID($pageId);
        $fileTitle = \Title::newFromText($filename, NS_FILE);
        
        $html = Html::openElement('div', [
            'class' => 'aspaklarya-queue-item',
            'data-id' => $id
        ]);
        
        if ($fileTitle && $fileTitle->exists()) {
            $file = wfFindFile($fileTitle);
            if ($file) {
                $thumb = $file->transform(['width' => 300]);
                if ($thumb) {
                    $html .= $thumb->toHtml(['class' => 'aspaklarya-queue-image']);
                }
            }
        }
        
        $html .= Html::element('h3', [], $filename);
        $html .= Html::element('div', ['class' => 'aspaklarya-queue-info'], 
            $this->msg('aspaklarya-queue-requested-by', $requester)->text());
        $html .= Html::element('div', ['class' => 'aspaklarya-queue-info'], 
            $this->msg('aspaklarya-queue-timestamp', wfTimestamp(TS_RFC2822, $timestamp))->text());
        
        if ($title) {
            $html .= Html::element('div', ['class' => 'aspaklarya-queue-info'], 
                $this->msg('aspaklarya-queue-page', $title->getPrefixedText())->text()
            );
        }
        
        $html .= '<div class="aspaklarya-queue-actions">';
        
        $removeButton = new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-remove')->text(),
            'flags' => ['destructive'],
            'classes' => ['aspaklarya-action-remove'],
            'data' => ['id' => $id]
        ]);
        $html .= $removeButton->toString();
        
        $approveButton = new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-approve')->text(),
            'flags' => ['progressive'],
            'classes' => ['aspaklarya-action-approve'],
            'data' => ['id' => $id]
        ]);
        $html .= $approveButton->toString();
        
        $editedButton = new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-edited')->text(),
            'flags' => [],
            'classes' => ['aspaklarya-action-edited'],
            'data' => ['id' => $id]
        ]);
        $html .= $editedButton->toString();
        
        $html .= '</div>';
        $html .= Html::closeElement('div');
        
        return $html;
    }

    protected function getGroupName() {
        return 'media';
    }
}