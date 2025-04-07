<?php

namespace MediaWiki\Extension\AspaklaryaReview\Api;

use ApiQueryBase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\ParamValidator\ParamValidator;
use MediaWiki\User\UserFactory;

class ApiQueryAspaklaryaLog extends ApiQueryBase {
    private $loadBalancer;
    private $userFactory;

    public function __construct(
        $query,
        $moduleName,
        ILoadBalancer $loadBalancer,
        UserFactory $userFactory
    ) {
        parent::__construct($query, $moduleName);
        $this->loadBalancer = $loadBalancer;
        $this->userFactory = $userFactory;
    }

    public function execute() {
        $params = $this->extractRequestParams();
        
        $db = $this->loadBalancer->getConnection(DB_REPLICA);
        
        $conds = [
            'log_type' => 'aspaklaryareview'
        ];
        
        if (isset($params['aslaction']) && $params['aslaction'] !== 'all') {
            $conds['log_action'] = $params['aslaction'];
        }
        
        if (isset($params['user']) && $params['user'] !== '') {
            $user = $this->userFactory->newFromName($params['user']);
            if ($user && $user->getId()) {
                $conds['log_user'] = $user->getId();
            } else {
                $this->dieWithError('Invalid username', 'invaliduser');
            }
        }
        
        if (isset($params['filename']) && $params['filename'] !== '') {
            $filenamePattern = $db->addQuotes('%' . $db->strencode($params['filename']) . '%');
            $conds[] = 'log_params LIKE ' . $filenamePattern;
        }

        $limit = $params['limit'];
        $this->addTables('logging');
        $this->addFields([
            'log_id',
            'log_type',
            'log_action',
            'log_timestamp',
            'log_user',
            'log_user_text',
            'log_params',
            'log_title',
            'log_namespace'
        ]);
        $this->addWhere($conds);
        $this->addOption('LIMIT', $limit);
        $this->addOption('ORDER BY', 'log_timestamp DESC');
        
        $res = $this->select(__METHOD__);
        
        $result = $this->getResult();
        $entries = [];
        
        foreach ($res as $row) {
            $entry = [
                'id' => $row->log_id,
                'timestamp' => wfTimestamp(TS_ISO_8601, $row->log_timestamp),
                'user' => $row->log_user_text,
                'action' => $row->log_action
            ];
            
            $params = [];
            if (!empty($row->log_params)) {
                $params = @json_decode($row->log_params, true);
                if (!is_array($params)) {
                    $params = [];
                }
            }
            
            if (is_array($params) && isset($params['filename'])) {
                $entry['filename'] = $params['filename'];
            }
            
            if ($row->log_namespace !== null && $row->log_title !== null) {
                $entry['title'] = \Title::makeTitle($row->log_namespace, $row->log_title)->getPrefixedText();
            }
            
            $entries[] = $entry;
        }
        
        $result->addValue('query', $this->getModuleName(), $entries);
    }

    public function getAllowedParams() {
        return [
            'aslaction' => [
                ParamValidator::PARAM_TYPE => ['all', 'submit', 'approved', 'removed', 'edited'],
                ParamValidator::PARAM_DEFAULT => 'all'
            ],
            'user' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'filename' => [
                ParamValidator::PARAM_TYPE => 'string',
                ParamValidator::PARAM_REQUIRED => false
            ],
            'limit' => [
                ParamValidator::PARAM_TYPE => 'limit',
                ParamValidator::PARAM_DEFAULT => 50,
                'min' => 1,
                'max' => 500,
                'apiMaxLimit' => 5000
            ]
        ];
    }

    protected function getExamplesMessages() {
        return [
            'action=query&list=aspaklaryalog'
                => 'apihelp-query+aspaklaryalog-example-simple',
            'action=query&list=aspaklaryalog&aslaction=approved'
                => 'apihelp-query+aspaklaryalog-example-byaction',
            'action=query&list=aspaklaryalog&user=Example'
                => 'apihelp-query+aspaklaryalog-example-byuser'
        ];
    }

    public function getHelpUrls() {
        return 'https://www.hamichlol.org.il/wiki/המכלול:הרחבת_בדיקת_תמונות/API#action=query&list=aspaklaryalog';
    }
}