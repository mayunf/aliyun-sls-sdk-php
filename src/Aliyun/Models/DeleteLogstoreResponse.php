<?php
/**
 * Copyright (C) Alibaba Cloud Computing
 * All rights reserved
 */

namespace Aliyun\SLS\Models;

/**
 * The response of the DeleteLogstore API from log service.
 *
 * @author log service dev
 */
class DeleteLogstoreResponse extends Response {
    
    /**
     * DeleteLogstoreResponse constructor
     *
     * @param array $resp
     *            DeleteLogstore HTTP response body
     * @param array $header
     *            DeleteLogstore HTTP response header
     */
    public function __construct($resp, $header) {
        parent::__construct ( $header );
    }
    
}
