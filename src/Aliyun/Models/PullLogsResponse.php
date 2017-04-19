<?php
/**
 * PullLogs response
 * User: moyo
 * Date: 20/04/2017
 * Time: 12:34 AM
 */

namespace Aliyun\SLS\Models;

use Aliyun\SLS\Proto\Log;
use Aliyun\SLS\Proto\Log_Content;
use Aliyun\SLS\Proto\LogGroup;
use Aliyun\SLS\Proto\LogGroupList;

class PullLogsResponse extends Response {

    private $count;

    private $cursor;

    /**
     * @var QueriedLog[]
     */
    private $logs;

    /**
     * PullLogsResponse constructor.
     * @param $resp
     * @param $headers
     */
    public function __construct($resp, $headers) {
        parent::__construct($headers);

        $this->count = $headers['x-log-count'];
        $this->cursor = $headers['x-log-cursor'];
        $this->logs = [];

        $logGroupList = new LogGroupList($resp);
        $glCount = $logGroupList->getLogGroupListCount();
        for ($gi = 0; $gi < $glCount; $gi ++) {
            /**
             * @var LogGroup $logGroup
             */
            $logGroup = $logGroupList->getLogGroupList($gi);
            $lgCount = $logGroup->getLogsCount();
            for ($li = 0; $li < $lgCount; $li ++) {
                /**
                 * @var Log $log
                 */
                $log = $logGroup->getLogs($li);
                $kvCount = $log->getContentsCount();
                $contents = ['__topic__' => $logGroup->getTopic()];
                for ($ki = 0; $ki < $kvCount; $ki ++) {
                    /**
                     * @var Log_Content $content;
                     */
                    $content = $log->getContents($ki);
                    $contents[$content->getKey()] = $content->getValue();
                }
                $this->logs[] = new QueriedLog($log->getTime(), $logGroup->getSource(), $contents);
            }
        }
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * @return string
     */
    public function getCursor()
    {
        return $this->cursor;
    }

    /**
     * @return QueriedLog[]
     */
    public function getLogs()
    {
        return $this->logs;
    }
}