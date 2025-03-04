<?php

namespace MediaWiki\Extension\AspaklaryaReview;

use SpecialPage;
use HTMLForm;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILoadBalancer;
use Status;
use OOUI;
use Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\ReverseChronologicalPager;

class AspaklaryaQueuePager extends ReverseChronologicalPager {
    private $userFactory;
    private $conditions;
    private $loadBalancer;
    private $parent;

    public function __construct($context, ILoadBalancer $loadBalancer, UserFactory $userFactory, $conditions = []) {
        parent::__construct($context);
        $this->loadBalancer = $loadBalancer;
        $this->userFactory = $userFactory;
        $this->conditions = $conditions;
    }

    public function setParent($parent) {
        $this->parent = $parent;
    }

    public function getParent() {
        return $this->parent;
    }

    public function getQueryInfo() {
        return [
            'tables' => 'aspaklarya_review_queue',
            'fields' => '*',
            'conds' => array_merge(['arq_status' => 'pending'], $this->conditions),
            'options' => []
        ];
    }

    public function getIndexField() {
        return 'arq_timestamp';
    }

    public function formatRow($row) {
        $requester = $this->userFactory->newFromId($row->arq_requester);
        $filename = $row->arq_filename;
        
        return $this->getParent()->formatQueueItem(
            $row->arq_id,
            $filename,
            $requester->getName(),
            $row->arq_timestamp,
            $row->arq_page_id
        );
    }

    public function getEmptyBody() {
        return Html::element(
            'div', 
            ['class' => 'aspaklarya-queue-empty'], 
            $this->msg('aspaklarya-queue-empty')->text()
        );
    }
}

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
        $request = $this->getRequest();

        $out->enableOOUI();
        $out->addModules(['ext.aspaklaryaQueue', 'oojs-ui-core', 'oojs-ui-widgets']);
        $out->setPageTitle($this->msg('aspaklarya-queue-title'));

        try {
            $pager = new AspaklaryaQueuePager($this->getContext(), $this->loadBalancer, $this->userFactory);
            $pager->setParent($this);
            $pager->setLimit(20); 
            
            if ($pager->getNumRows() > 0) {
                $out->addHTML(Html::rawElement('div', ['class' => 'aspaklarya-queue-list'], $pager->getBody()));
                $out->addHTML($pager->getNavigationBar());
            } else {
                $out->addWikiMsg('aspaklarya-queue-empty');
            }
        } catch (\Exception $e) {
            $out->addHTML(Html::errorBox('Error loading review queue: ' . $e->getMessage()));
        }
    }

    public function formatQueueItem($id, $filename, $requester, $timestamp, $pageId) {
        $title = \Title::newFromID($pageId);
        $fileTitle = \Title::newFromText($filename, NS_FILE);
        
        $html = Html::openElement('div', [
            'class' => 'aspaklarya-queue-item',
            'data-id' => $id
        ]);
        
        if ($fileTitle) {
            $services = MediaWikiServices::getInstance();
            $repoGroup = $services->getRepoGroup();
            $file = $repoGroup->findFile($fileTitle);

            if (!$file) {
                $file = $repoGroup->getForeignFile($fileTitle, 'shared');
            }
            
            if ($file) {
                $thumb = $file->transform(['width' => 300]);
                if ($thumb) {
                    $fileUrl = $file->getUrl();
                    $html .= Html::openElement('a', [
                        'href' => $fileUrl,
                        'class' => 'aspaklarya-queue-image-link'
                    ]);
                    $html .= $thumb->toHtml(['class' => 'aspaklarya-queue-image']);
                    $html .= Html::closeElement('a');
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
            'infusable' => true,
            'data' => ['id' => $id]
        ]);
        $html .= $removeButton->toString();
        
        $approveButton = new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-approve')->text(),
            'flags' => ['progressive'],
            'classes' => ['aspaklarya-action-approve'],
            'infusable' => true,
            'data' => ['id' => $id]
        ]);
        $html .= $approveButton->toString();
        
        $editedButton = new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-edited')->text(),
            'flags' => [],
            'classes' => ['aspaklarya-action-edited'],
            'infusable' => true,
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