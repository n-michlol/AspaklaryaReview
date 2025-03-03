<?php

namespace MediaWiki\Extension\AspaklaryaReview\Api;

use ApiQueryBase;
use Wikimedia\Rdbms\ILoadBalancer;

class ApiQueryAspaklaryaReview extends ApiQueryBase {
    private $loadBalancer;

    public function __construct(
        $query,
        $moduleName,
        ILoadBalancer $loadBalancer
    ) {
        parent::__construct($query, $moduleName);
        $this->loadBalancer = $loadBalancer;
    }

    public function execute() {
        $params = $this->extractRequestParams();
        $dbr = $this->loadBalancer->getConnection(DB_REPLICA);
        
        $conditions = ['arq_status' => 'pending'];
        
        if (isset($params['filename'])) {
            $conditions['arq_filename'] = $params['filename'];
        }
        
        if (isset($params['pageid'])) {
            $conditions['arq_page_id'] = $params['pageid'];
        }
        
        $res = $dbr->select(
            'aspaklarya_review_queue',
            '*',
            $conditions,
            __METHOD__
        );
        
        $result = [];
        
        foreach ($res as $row) {
            $result[] = [
                'id' => $row->arq_id,
                'filename' => $row->arq_filename,
                'pageid' => $row->arq_page_id,
                'status' => $row->arq_status,
                'timestamp' => wfTimestamp(TS_ISO_8601, $row->arq_timestamp)
            ];
        }
        
        $this->getResult()->addValue('query', $this->getModuleName(), $result);
    }

    public function getAllowedParams() {
        return [
            'filename' => [
                ApiQueryBase::PARAM_TYPE => 'string',
            ],
            'pageid' => [
                ApiQueryBase::PARAM_TYPE => 'integer',
            ],
        ];
    }

    protected function getExamplesMessages() {
        return [
            'action=query&list=aspaklaryareview&arqfilename=Example.jpg'
                => 'apihelp-query+aspaklaryareview-example-filename',
            'action=query&list=aspaklaryareview&arqpageid=123'
                => 'apihelp-query+aspaklaryareview-example-pageid'
        ];
    }
}