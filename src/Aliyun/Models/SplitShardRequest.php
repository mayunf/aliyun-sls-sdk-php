<?php
/**
 * Copyright (C) Alibaba Cloud Computing
 * All rights reserved
 */

namespace Aliyun\SLS\Models;

/**
 * 
 *
 * @author log service dev
 */
class SplitShardRequest extends Request {

    private $logstore;

    /**
     * SplitShardRequest Constructor
     *
     */
    public function __construct($project,$logstore,$shardId,$midHash) {
        parent::__construct ( $project );
        $this->logstore = $logstore;
        $this->shardId = $shardId;
        $this->midHash = $midHash;
    }

    public function getLogstore(){
      return $this->logstore;
    }

    public function setLogstore($logstore){
      $this->logstore = $logstore;
    }

    public function getShardId(){
        return $this->shardId;
    }
    public function getMidHash(){
        return $this->midHash;
    }
    
}
