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
class UpdateConfigRequest extends Request {

    private $config;
    /**
     * UpdateConfigRequest Constructor
     *
     */
    public function __construct($config) {
        $this->config = $config;
    }

    public function getConfig(){
        return $this->config;
    }

    public function setConfig($config){
        $this->config = $config;
    }
    
}
