<?php

namespace MediaWiki\Extension\AspaklaryaReview;

use SpecialPage;
use Html;
use OOUI;
use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\ReverseChronologicalPager;
use MediaWiki\Linker\Linker;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\ILoadBalancer;

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
        $out->addModules(['ext.aspaklaryaQueue', 'oojs-ui-core', 'oojs-ui-widgets', 'oojs-ui-windows']);
        $out->setPageTitle($this->msg('aspaklarya-queue-title'));

        try {
            $pager = new AspaklaryaQueuePager($this->getContext(), $this->loadBalancer, $this->userFactory);
            $pager->setParent($this);
            
            $limit = $request->getInt('limit', 20);
            $pager->setLimit($limit);

            $out->addHTML(Html::rawElement('div', ['class' => 'aspaklarya-queue-list'], $pager->getBody()));
            
            $out->addHTML($this->getCustomNavigationBar($pager));
        } catch (\Exception $e) {
            $out->addHTML(Html::errorBox('Error loading review queue: ' . $e->getMessage()));
        }
    }

    private function getCustomNavigationBar($pager) {
        $request = $this->getRequest();
        $limit = $pager->getLimit();
        $offset = $request->getInt('offset', 0);
        $numRows = $pager->getNumRows();

        $start = $offset + 1;
        $end = min($offset + $limit, $numRows);
        $rangeText = $this->msg('aspaklarya-queue-nav-range', $start, $end, $numRows)->text();

        $prevOffset = max($offset - $limit, 0);
        $nextOffset = $offset + $limit;

        $prevDisabled = $offset <= 0;
        $nextDisabled = $nextOffset >= $numRows;

        $prevButton = new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-nav-prev')->text(),
            'href' => $this->getPageTitle()->getLocalURL(['offset' => $prevOffset, 'limit' => $limit]),
            'disabled' => $prevDisabled,
            'icon' => 'previous',
            'flags' => ['progressive']
        ]);

        $nextButton = new OOUI\ButtonWidget([
            'label' => $this->msg('aspaklarya-queue-nav-next')->text(),
            'href' => $this->getPageTitle()->getLocalURL(['offset' => $nextOffset, 'limit' => $limit]),
            'disabled' => $nextDisabled,
            'icon' => 'next',
            'flags' => ['progressive']
        ]);

        $limitOptions = [10, 20, 25, 50, 100, 250, 500];
        $dropdownOptions = [];
        foreach ($limitOptions as $option) {
            $dropdownOptions[] = [
                'data' => $option,
                'label' => $this->msg('aspaklarya-queue-nav-per-page', $option)->text()
            ];
        }

        $limitDropdown = new OOUI\DropdownInputWidget([
            'name' => 'limit',
            'options' => $dropdownOptions,
            'value' => $limit,
            'classes' => ['aspaklarya-queue-nav-limit']
        ]);

        $goButton = new OOUI\ButtonWidget([
            'id' => 'aspaklarya-queue-nav-go',
            'label' => $this->msg('aspaklarya-queue-nav-go')->text(),
            'flags' => ['primary', 'progressive'],
            'type' => 'submit'
        ]);

        $form = new OOUI\FormLayout([
            'id' => 'aspaklarya-queue-nav-form',
            'method' => 'get',
            'action' => $this->getPageTitle()->getLocalURL(),
            'items' => [
                $limitDropdown,
                $goButton
            ],
            'classes' => ['aspaklarya-queue-nav-form']
        ]);

        $navHtml = Html::openElement('div', ['class' => 'aspaklarya-queue-navigation']);
        $navHtml .= Html::openElement('div', ['class' => 'aspaklarya-queue-nav-controls']);
        $navHtml .= $prevButton->toString();
        $navHtml .= Html::element('span', ['class' => 'aspaklarya-queue-nav-range'], $rangeText);
        $navHtml .= $nextButton->toString();
        $navHtml .= Html::closeElement('div');
        $navHtml .= Html::openElement('div', ['class' => 'aspaklarya-queue-nav-options']);
        $navHtml .= $form->toString();
        $navHtml .= Html::closeElement('div');
        $navHtml .= Html::closeElement('div');

        return $navHtml;
    }

    public function formatQueueItem($id, $filename, $requester, $timestamp, $pageId) {
        $title = \Title::newFromID($pageId);
        $fileTitle = \Title::newFromText($filename, NS_FILE);
        
        $html = Html::openElement('div', [
            'class' => 'aspaklarya-queue-item',
            'data-id' => $id
        ]);
        
        $dbr = $this->loadBalancer->getConnection(DB_REPLICA);
        $previousReview = $dbr->selectRow(
            'aspaklarya_review_queue',
            '*',
            [
                'arq_filename' => $filename,
                'arq_status' => ['approved', 'removed', 'edited']
            ],
            __METHOD__,
            [
                'ORDER BY' => 'arq_review_timestamp DESC'
            ]
        );
        
        if ($previousReview) {
            $reviewer = $this->userFactory->newFromId($previousReview->arq_reviewer);
            $reviewerName = $reviewer ? $reviewer->getName() : '(unknown)';
            $language = $this->getLanguage();
            $formattedReviewDate = $language->userTimeAndDate($previousReview->arq_review_timestamp, $this->getUser());
        
            $html .= Html::rawElement('div', [
                'class' => 'aspaklarya-queue-previous-review aspaklarya-status-' . $previousReview->arq_status
            ], $this->msg('aspaklarya-queue-previously-reviewed', 
                $previousReview->arq_status, 
                $formattedReviewDate, 
                $reviewerName
            )->parse());
        }

        if ($fileTitle) {
            $services = MediaWikiServices::getInstance();
            $repoGroup = $services->getRepoGroup();
            $file = $repoGroup->findFile($fileTitle);

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
            } else {
                $html .= Html::element('div', ['class' => 'aspaklarya-queue-error'], 
                    $this->msg('aspaklarya-queue-error-processing')->text());
            }
        }
        
        $fileLink = Linker::link($fileTitle, htmlspecialchars($filename));
        $html .= Html::rawElement('h3', [], $fileLink);

        $html .= Html::rawElement('div', ['class' => 'aspaklarya-queue-info'], 
            $this->msg('aspaklarya-queue-requested-by', $requester)->parse());
        
        $language = $this->getLanguage();
        $user = $this->getUser();
        $formattedDate = $language->userTimeAndDate($timestamp, $user);
        
        $html .= Html::element('div', ['class' => 'aspaklarya-queue-info'], 
            $this->msg('aspaklarya-queue-timestamp', $formattedDate)->text());
        
        if ($title) {
            $html .= Html::rawElement('div', ['class' => 'aspaklarya-queue-info'], 
                $this->msg('aspaklarya-queue-page', $title->getPrefixedText())->parse());
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