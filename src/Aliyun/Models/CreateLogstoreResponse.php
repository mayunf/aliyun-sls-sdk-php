<?php
/**
 * Copyright (C) Alibaba Cloud Computing
 * All rights reserved
 */

namespace Aliyun\SLS\Models;

/**
 * The response of the CreateLogstore API from log service.
 *
 * @author log service dev
 */
class CreateLogstoreResponse extends Response {
    
    /**
     * CreateLogstoreResponse constructor
     *
     * @param array $resp
     *            CreateLogstore HTTP response body
     * @param array $header
     *            CreateLogstore HTTP response header
     */
    public function __construct($resp, $header) {
        parent::__construct ( $header );
    }
    
}
