<?php
/**
 * PullLogs request
 * User: moyo
 * Date: 20/04/2017
 * Time: 12:17 AM
 */

namespace Aliyun\SLS\Models;

class PullLogsRequest extends Request {

    private $logstore;

    private $type = 'logs';

    private $shard;

    private $cursor;

    private $count = 100;

    /**
     * PullLogsRequest constructor.
     * @param string $project
     * @param $logstore
     * @param $shard
     * @param $cursor
     * @param $count
     */
    public function __construct($project, $logstore, $shard, $cursor, $count = null) {
        parent::__construct($project);

        $this->logstore = $logstore;
        $this->shard = $shard;
        $this->cursor = $cursor;

        if (is_numeric($count))
        {
            $this->count = $count;
        }
    }

    /**
     * @return string
     */
    public function getLogstore() {
        return $this->logstore;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getShard() {
        return $this->shard;
    }

    /**
     * @return string
     */
    public function getCursor() {
        return $this->cursor;
    }

    /**
     * @return int
     */
    public function getCount() {
        return $this->count;
    }
}