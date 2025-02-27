<?php

namespace MediaWiki\Extension\AspaklaryaReview;

use SpecialPage;
use HTMLForm;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILoadBalancer;
use Status;

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
        
        $out->addModules(['ext.aspaklaryaReview']);
        $out->setPageTitle($this->msg('aspaklarya-queue-title'));

        $dbr = $this->loadBalancer->getConnection(DB_REPLICA);
        
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
        $html .= Html::element('p', [], $this->msg('aspaklarya-queue-requested-by', $requester)->text());
        $html .= Html::element('p', [], $this->msg('aspaklarya-queue-timestamp', $timestamp)->text());
        
        if ($title) {
            $html .= Html::element('p', [], 
                $this->msg('aspaklarya-queue-page', $title->getPrefixedText())->text()
            );
        }
        
        $html .= Html::openElement('div', ['class' => 'aspaklarya-queue-actions']);
        
        $html .= new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-remove')->text(),
            'flags' => ['progressive'],
            'classes' => ['aspaklarya-action-remove'],
            'data' => ['id' => $id]
        ]);
        
        $html .= new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-approve')->text(),
            'flags' => ['progressive'],
            'classes' => ['aspaklarya-action-approve'],
            'data' => ['id' => $id]
        ]);
        
        $html .= new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-edited')->text(),
            'flags' => ['progressive'],
            'classes' => ['aspaklarya-action-edited'],
            'data' => ['id' => $id]
        ]);
        
        $html .= Html::closeElement('div');
        $html .= Html::closeElement('div');
        
        return $html;
    }
}