<?php
/**
 * Copyright (C) Alibaba Cloud Computing
 * All rights reserved
 */

namespace Aliyun\SLS;

use Aliyun\SLS\Models\GetCursorRequest;
use Aliyun\SLS\Models\GetCursorResponse;
use Aliyun\SLS\Models\GetLogsRequest;
use Aliyun\SLS\Models\GetLogsResponse;
use Aliyun\SLS\Models\ListShardsRequest;
use Aliyun\SLS\Models\ListShardsResponse;
use Aliyun\SLS\Models\PullLogsRequest;
use Aliyun\SLS\Models\PullLogsResponse;
use Aliyun\SLS\Models\PutLogsRequest;
use Aliyun\SLS\Models\PutLogsResponse;
use Aliyun\SLS\Request\RequestCore;
use Aliyun\SLS\Proto\Log;
use Aliyun\SLS\Proto\Log_Content;
use Aliyun\SLS\Proto\LogGroup;
use Aliyun\SLS\Proto\LogGroupList;

use Exception as SysException;

/**
 * Aliyun_Log_Client class is the main class in the SDK. It can be used to
 * communicate with LOG server to put/get data.
 *
 * @author log_dev
 */
class Client {

    const API_VERSION = '0.6.0';

    const USER_AGENT = 'log-php-sdk-v-0.6.0';

    /**
     * @var string aliyun accessKey
     */
    protected $accessKey;
    
    /**
     * @var string aliyun accessKeyId
     */
    protected $accessKeyId;

    /**
     *@var string aliyun sts token
     */
    protected $stsToken;

    /**
     * @var string LOG endpoint
     */
    protected $endpoint;

    /**
     * @var string Check if the host if row ip.
     */
    protected $isRowIp;

    /**
     * @var integer Http send port. The dafault value is 80.
     */
    protected $port;

    /**
     * @var string log sever host.
     */
    protected $logHost;

    /**
     * @var string the local machine ip address.
     */
    protected $source;
    
    /**
     * Aliyun_Log_Client constructor
     *
     * @param string $endpoint
     *            LOG host name, for example, http://cn-hangzhou.sls.aliyuncs.com
     * @param string $accessKeyId
     *            aliyun accessKeyId
     * @param string $accessKey
     *            aliyun accessKey
     */
    public function __construct($endpoint, $accessKeyId, $accessKey,$token = "") {
        $this->setEndpoint ( $endpoint ); // set $this->logHost
        $this->accessKeyId = $accessKeyId;
        $this->accessKey = $accessKey;
        $this->stsToken = $token;
        $this->source = Util::getLocalIp();
    }
    private function setEndpoint($endpoint) {
        $pos = strpos ( $endpoint, "://" );
        if ($pos !== false) { // be careful, !==
            $pos += 3;
            $endpoint = substr ( $endpoint, $pos );
        }
        $pos = strpos ( $endpoint, "/" );
        if ($pos !== false) // be careful, !==
            $endpoint = substr ( $endpoint, 0, $pos );
        $pos = strpos ( $endpoint, ':' );
        if ($pos !== false) { // be careful, !==
            $this->port = ( int ) substr ( $endpoint, $pos + 1 );
            $endpoint = substr ( $endpoint, 0, $pos );
        } else
            $this->port = 80;
        $this->isRowIp = Util::isIp ( $endpoint );
        $this->logHost = $endpoint;
        $this->endpoint = $endpoint . ':' . ( string ) $this->port;
    }
     
    /**
     * GMT format time string.
     * 
     * @return string
     */
    protected function getGMT() {
        return gmdate ( 'D, d M Y H:i:s' ) . ' GMT';
    }
    

    /**
     * Decodes a JSON string to a JSON Object. 
     * Unsuccessful decode will cause an Exception.
     * 
     * @return string
     * @throws Exception
     */
    protected function parseToJson($resBody, $requestId) {
        if (! $resBody)
          return NULL;
        
        $result = json_decode ( $resBody, true );
        if ($result === NULL){
          throw new Exception ( 'BadResponse', "Bad format,not json: $resBody", $requestId );
        }
        return $result;
    }
    
    /**
     * @return array
     */
    protected function getHttpResponse($method, $url, $body, $headers) {
        $request = new RequestCore ( $url );
        foreach ( $headers as $key => $value )
            $request->add_header ( $key, $value );
        $request->set_method ( $method );
        $request->set_useragent(self::USER_AGENT);
        if ($method == "POST" || $method == "PUT")
            $request->set_body ( $body );
        $request->send_request ();
        $response = array ();
        $response [] = ( int ) $request->get_response_code ();
        $response [] = $request->get_response_header ();
        $response [] = $request->get_response_body ();
        return $response;
    }
    
    /**
     * @return array
     * @throws Exception
     */
    private function sendRequest($method, $url, $body, $headers) {
        try {
            list ( $responseCode, $header, $resBody ) = 
                    $this->getHttpResponse ( $method, $url, $body, $headers );
        } catch ( SysException $ex ) {
            throw new Exception ( $ex->getMessage (), $ex->__toString () );
        }
        
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';

        if ($responseCode == 200) {
          return array ($resBody,$header);
        } 
        else {
            $exJson = $this->parseToJson ( $resBody, $requestId );
            if (isset($exJson ['error_code']) && isset($exJson ['error_message'])) {
                throw new Exception ( $exJson ['error_code'], 
                        $exJson ['error_message'], $requestId );
            } else {
                if ($exJson) {
                    $exJson = ' The return json is ' . json_encode($exJson);
                } else {
                    $exJson = '';
                }
                throw new Exception ( 'RequestError',
                        "Request is failed. Http code is $responseCode.$exJson", $requestId );
            }
        }
    }
    
    /**
     * @return array
     * @throws Exception
     */
    private function send($method, $project, $body, $resource, $params, $headers) {
        if ($body) {
            $headers ['Content-Length'] = strlen ( $body );
            if(isset($headers ["x-log-bodyrawsize"])==false)
                $headers ["x-log-bodyrawsize"] = 0;
            $headers ['Content-MD5'] = Util::calMD5 ( $body );
        } else {
            $headers ['Content-Length'] = 0;
            $headers ["x-log-bodyrawsize"] = 0;
            $headers ['Content-Type'] = ''; // If not set, http request will add automatically.
        }
        
        $headers ['x-log-apiversion'] = self::API_VERSION;
        $headers ['x-log-signaturemethod'] = 'hmac-sha1';
        if(strlen($this->stsToken) >0)
            $headers ['x-acs-security-token'] = $this -> stsToken;
        if(is_null($project))$headers ['Host'] = $this->logHost;
        else $headers ['Host'] = "$project.$this->logHost";
        $headers ['Date'] = $this->GetGMT ();
        $signature = Util::getRequestAuthorization ( $method, $resource, $this->accessKey,$this->stsToken, $params, $headers );
        $headers ['Authorization'] = "LOG $this->accessKeyId:$signature";
        
        $url = $resource;
        if ($params)
            $url .= '?' . Util::urlEncode ( $params );
        if ($this->isRowIp)
            $url = "http://$this->endpoint$url";
        else{
          if(is_null($project))
              $url = "http://$this->endpoint$url";
          else  $url = "http://$project.$this->endpoint$url";           
        }
        return $this->sendRequest ( $method, $url, $body, $headers );
    }
    
    /**
     * Put logs to Log Service.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param PutLogsRequest $request the PutLogs request parameters class
     * @throws Exception
     * @return PutLogsResponse
     */
    public function putLogs(PutLogsRequest $request) {
        if (count ( $request->getLogitems () ) > 4096)
            throw new Exception ( 'InvalidLogSize', "logItems' length exceeds maximum limitation: 4096 lines." );
        
        $logGroup = new LogGroup ();
        $topic = $request->getTopic () !== null ? $request->getTopic () : '';
        $logGroup->setTopic ( $request->getTopic () );
        $source = $request->getSource ();
        
        if ( ! $source )
            $source = $this->source;
        $logGroup->setSource ( $source );
        $logitems = $request->getLogitems ();
        foreach ( $logitems as $logItem ) {
            $log = new Log ();
            $log->setTime ( $logItem->getTime () );
            $content = $logItem->getContents ();
            foreach ( $content as $key => $value ) {
                $content = new Log_Content ();
                $content->setKey ( $key );
                $content->setValue ( $value );
                $log->addContents ( $content );
            }

            $logGroup->addLogs ( $log );
        }

        $body = Util::toBytes ( $logGroup );
        unset ( $logGroup );
        
        $bodySize = strlen ( $body );
        if ($bodySize > 3 * 1024 * 1024) // 3 MB
            throw new Exception ( 'InvalidLogSize', "logItems' size exceeds maximum limitation: 3 MB." );
        $params = array ();
        $headers = array ();
        $headers ["x-log-bodyrawsize"] = $bodySize;
        $headers ['x-log-compresstype'] = 'deflate';
        $headers ['Content-Type'] = 'application/x-protobuf';
        $body = gzcompress ( $body, 6 );
        
        $logstore = $request->getLogstore () !== null ? $request->getLogstore () : '';
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $shardKey = $request -> getShardKey();
        $resource = "/logstores/" . $logstore.($shardKey== null?"/shards/lb":"/shards/route");
        if($shardKey)
            $params["key"]=$shardKey;
        list ( $resp, $header ) = $this->send ( "POST", $project, $body, $resource, $params, $headers );
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new PutLogsResponse ( $header );
    }

    /**
     * create logstore 
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param CreateLogstoreRequest $request the CreateLogStore request parameters class.
     * @throws Exception
     * return CreateLogstoreResponse
     */
    public function createLogstore(CreateLogstoreRequest $request){
        $headers = array ();
        $params = array ();
        $resource = '/logstores';
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $headers["x-log-bodyrawsize"] = 0;
        $headers["Content-Type"] = "application/json";
        $body = array(
            "logstoreName" => $request -> getLogstore(),
            "ttl" => (int)($request -> getTtl()),
            "shardCount" => (int)($request -> getShardCount())
        );
        $body_str =  json_encode($body);
        list($resp,$header)  = $this -> send("POST",$project,$body_str,$resource,$params,$headers);
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new CreateLogstoreResponse($resp,$header);
    }
    /**
     * update logstore 
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param UpdateLogstoreRequest $request the UpdateLogStore request parameters class.
     * @throws Exception
     * return UpdateLogstoreResponse
     */
    public function updateLogstore(UpdateLogstoreRequest $request){
        $headers = array ();
        $params = array ();
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $headers["x-log-bodyrawsize"] = 0;
        $headers["Content-Type"] = "application/json";
        $body = array(
            "logstoreName" => $request -> getLogstore(),
            "ttl" => (int)($request -> getTtl()),
            "shardCount" => (int)($request -> getShardCount())
        );
        $resource = '/logstores/'.$request -> getLogstore();
        $body_str =  json_encode($body);
        list($resp,$header)  = $this -> send("PUT",$project,$body_str,$resource,$params,$headers);
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new UpdateLogstoreResponse($resp,$header);
    }
    /**
     * List all logstores of requested project.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param ListLogstoresRequest $request the ListLogstores request parameters class.
     * @throws Exception
     * @return ListLogstoresResponse
     */
    public function listLogstores(ListLogstoresRequest $request) {
        $headers = array ();
        $params = array ();
        $resource = '/logstores';
        $project = $request->getProject () !== null ? $request->getProject () : '';
        list ( $resp, $header ) = $this->send ( "GET", $project, NULL, $resource, $params, $headers );
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new ListLogstoresResponse ( $resp, $header );
    }

    /**
     * Delete logstore
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param DeleteLogstoreRequest $request the DeleteLogstores request parameters class.
     * @throws Exception
     * @return DeleteLogstoresResponse
     */
    public function deleteLogstore(DeleteLogstoreRequest $request) {
        $headers = array ();
        $params = array ();
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $logstore = $request -> getLogstore() != null ? $request -> getLogstore() :"";
        $resource = "/logstores/$logstore";
        list ( $resp, $header ) = $this->send ( "DELETE", $project, NULL, $resource, $params, $headers );
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new DeleteLogstoreResponse ( $resp, $header );
    }

    /**
     * List all topics in a logstore.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param ListTopicsRequest $request the ListTopics request parameters class.
     * @throws Exception
     * @return ListTopicsResponse
     */
    public function listTopics(ListTopicsRequest $request) {
        $headers = array ();
        $params = array ();
        if ($request->getToken () !== null)
            $params ['token'] = $request->getToken ();
        if ($request->getLine () !== null)
            $params ['line'] = $request->getLine ();
        $params ['type'] = 'topic';
        $logstore = $request->getLogstore () !== null ? $request->getLogstore () : '';
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $resource = "/logstores/$logstore";
        list ( $resp, $header ) = $this->send ( "GET", $project, NULL, $resource, $params, $headers );
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new ListTopicsResponse ( $resp, $header );
    }

    /**
     * Get histograms of requested query from log service.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param GetHistogramsRequest $request the GetHistograms request parameters class.
     * @throws Exception
     * @return array(json body, http header)
     */
    public function getHistogramsJson(GetHistogramsRequest $request) {
        $headers = array ();
        $params = array ();
        if ($request->getTopic () !== null)
            $params ['topic'] = $request->getTopic ();
        if ($request->getFrom () !== null)
            $params ['from'] = $request->getFrom ();
        if ($request->getTo () !== null)
            $params ['to'] = $request->getTo ();
        if ($request->getQuery () !== null)
            $params ['query'] = $request->getQuery ();
        $params ['type'] = 'histogram';
        $logstore = $request->getLogstore () !== null ? $request->getLogstore () : '';
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $resource = "/logstores/$logstore";
        list ( $resp, $header ) = $this->send ( "GET", $project, NULL, $resource, $params, $headers );
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return array($resp, $header);
    }
    
    /**
     * Get histograms of requested query from log service.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param GetHistogramsRequest $request the GetHistograms request parameters class.
     * @throws Exception
     * @return GetHistogramsResponse
     */
    public function getHistograms(GetHistogramsRequest $request) {
        $ret = $this->getHistogramsJson($request);
        $resp = $ret[0];
        $header = $ret[1];
        return new GetHistogramsResponse ( $resp, $header );
    }

    /**
     * Get logs from Log service.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param GetLogsRequest $request the GetLogs request parameters class.
     * @throws Exception
     * @return array(json body, http header)
     */
    public function getLogsJson(GetLogsRequest $request) {
        $headers = array ();
        $params = array ();
        if ($request->getTopic () !== null)
            $params ['topic'] = $request->getTopic ();
        if ($request->getFrom () !== null)
            $params ['from'] = $request->getFrom ();
        if ($request->getTo () !== null)
            $params ['to'] = $request->getTo ();
        if ($request->getQuery () !== null)
            $params ['query'] = $request->getQuery ();
        $params ['type'] = 'log';
        if ($request->getLine () !== null)
            $params ['line'] = $request->getLine ();
        if ($request->getOffset () !== null)
            $params ['offset'] = $request->getOffset ();
        if ($request->getOffset () !== null)
            $params ['reverse'] = $request->getReverse () ? 'true' : 'false';
        $logstore = $request->getLogstore () !== null ? $request->getLogstore () : '';
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $resource = "/logstores/$logstore";
        list ( $resp, $header ) = $this->send ( "GET", $project, NULL, $resource, $params, $headers );
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return array($resp, $header);
        //return new GetLogsResponse ( $resp, $header );
    }
    
    /**
     * Get logs from Log service.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param GetLogsRequest $request the GetLogs request parameters class.
     * @throws Exception
     * @return GetLogsResponse
     */
    public function getLogs(GetLogsRequest $request) {
        $ret = $this->getLogsJson($request);
        $resp = $ret[0];
        $header = $ret[1];
        return new GetLogsResponse ( $resp, $header );
    }

    /**
     * Pull logs from Log service.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param PullLogsRequest $request
     * @return PullLogsResponse
     */
    public function pullLogs(PullLogsRequest $request) {
        $headers = array ();
        $headers['Accept'] = 'application/x-protobuf';
        $headers['Accept-Encoding'] = 'deflate';

        $params = array ();
        $params['type'] = $request->getType();
        $params['cursor'] = $request->getCursor();
        $params['count'] = $request->getCount();

        $logstore = $request->getLogstore () !== null ? $request->getLogstore () : '';
        $project = $request->getProject () !== null ? $request->getProject () : '';
        $shard = $request->getShard() !== null ? $request->getShard() : 0;
        $resource = "/logstores/$logstore/shards/$shard";
        list ( $resp, $header ) = $this->send ( "GET", $project, NULL, $resource, $params, $headers );

        if (isset($header['x-log-compresstype']) && $header['x-log-compresstype'] == 'deflate') {
            $resp = gzuncompress($resp);
        }

        return new PullLogsResponse($resp, $header);
    }
    
    
    /**
     * Get logs from Log service with shardid conditions.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param BatchGetLogsRequest $request the BatchGetLogs request parameters class.
     * @throws Exception
     * @return BatchGetLogsResponse
     */
    public function batchGetLogs(BatchGetLogsRequest $request) {
      $params = array();
      $headers = array();
      $project = $request->getProject()!==null?$request->getProject():'';
      $logstore = $request->getLogstore()!==null?$request->getLogstore():'';
      $shardId = $request->getShardId()!==null?$request->getShardId():'';
      if($request->getCount()!==null)
          $params['count']=$request->getCount();
      if($request->getCursor()!==null)
          $params['cursor']=$request->getCursor();
      $params['type']='log';
      $headers['Accept-Encoding']='gzip';
      $headers['accept']='application/x-protobuf';

      $resource = "/logstores/$logstore/shards/$shardId";
      list($resp,$header) = $this->send("GET",$project,NULL,$resource,$params,$headers);
      //$resp is a byteArray
      $resp =  gzuncompress($resp);
      if($resp===false)$resp = new LogGroupList();
      
      else {
          $resp = new LogGroupList($resp);
      }
      return new BatchGetLogsResponse ( $resp, $header );
    }

    /**
     * List Shards from Log service with Project and logstore conditions.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param ListShardsRequest $request the ListShards request parameters class.
     * @throws Exception
     * @return ListShardsResponse
     */
    public function listShards(ListShardsRequest $request) {
        $params = array();
        $headers = array();
        $project = $request->getProject()!==null?$request->getProject():'';
        $logstore = $request->getLogstore()!==null?$request->getLogstore():'';

        $resource='/logstores/'.$logstore.'/shards';
        list($resp,$header) = $this->send("GET",$project,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new ListShardsResponse ( $resp, $header );
    }

    /**
     * split a shard into two shards  with Project and logstore and shardId and midHash conditions.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param SplitShardRequest $request the SplitShard request parameters class.
     * @throws Exception
     * @return ListShardsResponse
     */
    public function splitShard(SplitShardRequest $request) {
        $params = array();
        $headers = array();
        $project = $request->getProject()!==null?$request->getProject():'';
        $logstore = $request->getLogstore()!==null?$request->getLogstore():'';
        $shardId = $request -> getShardId()!= null ? $request -> getShardId():-1;
        $midHash = $request -> getMidHash()!= null?$request -> getMidHash():"";

        $resource='/logstores/'.$logstore.'/shards/'.$shardId;
        $params["action"] = "split";
        $params["key"] = $midHash;
        list($resp,$header) = $this->send("POST",$project,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new ListShardsResponse ( $resp, $header );
    }
    /**
     * merge two shards into one shard with Project and logstore and shardId and conditions.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param MergeShardsRequest $request the MergeShards request parameters class.
     * @throws Exception
     * @return ListShardsResponse
     */
    public function MergeShards(MergeShardsRequest $request) {
        $params = array();
        $headers = array();
        $project = $request->getProject()!==null?$request->getProject():'';
        $logstore = $request->getLogstore()!==null?$request->getLogstore():'';
        $shardId = $request -> getShardId()!= null ? $request -> getShardId():-1;

        $resource='/logstores/'.$logstore.'/shards/'.$shardId;
        $params["action"] = "merge";
        list($resp,$header) = $this->send("POST",$project,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new ListShardsResponse ( $resp, $header );
    }
    /**
     * delete a read only shard with Project and logstore and shardId conditions.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param DeleteShardRequest $request the DeleteShard request parameters class.
     * @throws Exception
     * @return ListShardsResponse
     */
    public function DeleteShard(DeleteShardRequest $request) {
        $params = array();
        $headers = array();
        $project = $request->getProject()!==null?$request->getProject():'';
        $logstore = $request->getLogstore()!==null?$request->getLogstore():'';
        $shardId = $request -> getShardId()!= null ? $request -> getShardId():-1;

        $resource='/logstores/'.$logstore.'/shards/'.$shardId;
        list($resp,$header) = $this->send("DELETE",$project,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        return new DeleteShardResponse ( $header );
    }

    /**
     * Get cursor from Log service.
     * Unsuccessful opertaion will cause an Exception.
     *
     * @param GetCursorRequest $request the GetCursor request parameters class.
     * @throws Exception
     * @return GetCursorResponse
     */
    public function getCursor(GetCursorRequest $request){
      $params = array();
      $headers = array();
      $project = $request->getProject()!==null?$request->getProject():'';
      $logstore = $request->getLogstore()!==null?$request->getLogstore():'';
      $shardId = $request->getShardId()!==null?$request->getShardId():'';
      $mode = $request->getMode()!==null?$request->getMode():'';
      $fromTime = $request->getFromTime()!==null?$request->getFromTime():-1;

      if((empty($mode) xor $fromTime==-1)==false){
        if(!empty($mode))
          throw new Exception ( 'RequestError',"Request is failed. Mode and fromTime can not be not empty simultaneously");
        else
          throw new Exception ( 'RequestError',"Request is failed. Mode and fromTime can not be empty simultaneously");
      }
      if(!empty($mode) && strcmp($mode,'begin')!==0 && strcmp($mode,'end')!==0)
        throw new Exception ( 'RequestError',"Request is failed. Mode value invalid:$mode");
      if($fromTime!==-1 && (is_integer($fromTime)==false || $fromTime<0))
        throw new Exception ( 'RequestError',"Request is failed. FromTime value invalid:$fromTime");
      $params['type']='cursor';
      if($fromTime!==-1)$params['from']=$fromTime;
      else $params['from'] = $mode;
      $resource='/logstores/'.$logstore.'/shards/'.$shardId;
      list($resp,$header) = $this->send("GET",$project,NULL,$resource,$params,$headers); 
      $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
      $resp = $this->parseToJson ( $resp, $requestId );
      return new GetCursorResponse($resp,$header);
    }

    public function createConfig(CreateConfigRequest $request){
        $params = array();
        $headers = array();
        $body=null;
        if($request->getConfig()!==null){
          $body = json_encode($request->getConfig()->toArray());
        }
        $headers ['Content-Type'] = 'application/json';
        $resource = '/configs';
        list($resp,$header) = $this->send("POST",NULL,$body,$resource,$params,$headers); 
        return new CreateConfigResponse($header);
    }

    public function updateConfig(UpdateConfigRequest $request){
        $params = array();
        $headers = array();
        $body=null;
        $configName='';
        if($request->getConfig()!==null){
          $body = json_encode($request->getConfig()->toArray());
          $configName=($request->getConfig()->getConfigName()!==null)?$request->getConfig()->getConfigName():'';
        }
        $headers ['Content-Type'] = 'application/json';
        $resource = '/configs/'.$configName;
        list($resp,$header) = $this->send("PUT",NULL,$body,$resource,$params,$headers);  
        return new UpdateConfigResponse($header);
    }

    public function getConfig(GetConfigRequest $request){
        $params = array();
        $headers = array();

        $configName = ($request->getConfigName()!==null)?$request->getConfigName():'';
        
        $resource = '/configs/'.$configName;
        list($resp,$header) = $this->send("GET",NULL,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new GetConfigResponse($resp,$header);
    }

    public function deleteConfig(DeleteConfigRequest $request){
        $params = array();
        $headers = array();
        $configName = ($request->getConfigName()!==null)?$request->getConfigName():'';
        $resource = '/configs/'.$configName;
        list($resp,$header) = $this->send("DELETE",NULL,NULL,$resource,$params,$headers); 
        return new DeleteConfigResponse($header);
    }

    public function listConfigs(ListConfigsRequest $request){
        $params = array();
        $headers = array();

        if($request->getConfigName()!==null)$params['configName'] = $request->getConfigName();
        if($request->getOffset()!==null)$params['offset'] = $request->getOffset();
        if($request->getSize()!==null)$params['size'] = $request->getSize();

        $resource = '/configs';
        list($resp,$header) = $this->send("GET",NULL,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new ListConfigsResponse($resp,$header);
    }
    
    public function createMachineGroup(CreateMachineGroupRequest $request){
        $params = array();
        $headers = array();
        $body=null;
        if($request->getMachineGroup()!==null){
          $body = json_encode($request->getMachineGroup()->toArray());
        }
        $headers ['Content-Type'] = 'application/json';
        $resource = '/machinegroups';
        list($resp,$header) = $this->send("POST",NULL,$body,$resource,$params,$headers); 

        return new CreateMachineGroupResponse($header);
    }

    public function updateMachineGroup(UpdateMachineGroupRequest $request){
        $params = array();
        $headers = array();
        $body=null;
        $groupName='';
        if($request->getMachineGroup()!==null){
          $body = json_encode($request->getMachineGroup()->toArray());
          $groupName=($request->getMachineGroup()->getGroupName()!==null)?$request->getMachineGroup()->getGroupName():'';
        }
        $headers ['Content-Type'] = 'application/json';
        $resource = '/machinegroups/'.$groupName;
        list($resp,$header) = $this->send("PUT",NULL,$body,$resource,$params,$headers);  
        return new UpdateMachineGroupResponse($header);
    }

    public function getMachineGroup(GetMachineGroupRequest $request){
        $params = array();
        $headers = array();

        $groupName = ($request->getGroupName()!==null)?$request->getGroupName():'';
        
        $resource = '/machinegroups/'.$groupName;
        list($resp,$header) = $this->send("GET",NULL,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new GetMachineGroupResponse($resp,$header);
    }

    public function deleteMachineGroup(DeleteMachineGroupRequest $request){
        $params = array();
        $headers = array();

        $groupName = ($request->getGroupName()!==null)?$request->getGroupName():'';
        $resource = '/machinegroups/'.$groupName;
        list($resp,$header) = $this->send("DELETE",NULL,NULL,$resource,$params,$headers); 
        return new DeleteMachineGroupResponse($header);
    }

    public function listMachineGroups(ListMachineGroupsRequest $request){
        $params = array();
        $headers = array();

        if($request->getGroupName()!==null)$params['groupName'] = $request->getGroupName();
        if($request->getOffset()!==null)$params['offset'] = $request->getOffset();
        if($request->getSize()!==null)$params['size'] = $request->getSize();

        $resource = '/machinegroups';
        list($resp,$header) = $this->send("GET",NULL,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );

        return new ListMachineGroupsResponse($resp,$header);
    }

    public function applyConfigToMachineGroup(ApplyConfigToMachineGroupRequest $request){
        $params = array();
        $headers = array();
        $configName=$request->getConfigName();
        $groupName=$request->getGroupName();
        $headers ['Content-Type'] = 'application/json';
        $resource = '/machinegroups/'.$groupName.'/configs/'.$configName;
        list($resp,$header) = $this->send("PUT",NULL,NULL,$resource,$params,$headers);  
        return new ApplyConfigToMachineGroupResponse($header);
    }

    public function removeConfigFromMachineGroup(RemoveConfigFromMachineGroupRequest $request){
        $params = array();
        $headers = array();
        $configName=$request->getConfigName();
        $groupName=$request->getGroupName();
        $headers ['Content-Type'] = 'application/json';
        $resource = '/machinegroups/'.$groupName.'/configs/'.$configName;
        list($resp,$header) = $this->send("DELETE",NULL,NULL,$resource,$params,$headers);  
        return new RemoveConfigFromMachineGroupResponse($header);
    }

    public function getMachine(GetMachineRequest $request){
        $params = array();
        $headers = array();

        $uuid = ($request->getUuid()!==null)?$request->getUuid():'';

        $resource = '/machines/'.$uuid;
        list($resp,$header) = $this->send("GET",NULL,NULL,$resource,$params,$headers);
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new GetMachineResponse($resp,$header);
    }

    public function createACL(CreateACLRequest $request){
        $params = array();
        $headers = array();
        $body=null;
        if($request->getAcl()!==null){
          $body = json_encode($request->getAcl()->toArray());
        }
        $headers ['Content-Type'] = 'application/json';
        $resource = '/acls';
        list($resp,$header) = $this->send("POST",NULL,$body,$resource,$params,$headers);
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new CreateACLResponse($resp,$header);
    }

    public function updateACL(UpdateACLRequest $request){
        $params = array();
        $headers = array();
        $body=null;
        $aclId='';
        if($request->getAcl()!==null){
          $body = json_encode($request->getAcl()->toArray());
          $aclId=($request->getAcl()->getAclId()!==null)?$request->getAcl()->getAclId():'';
        }
        $headers ['Content-Type'] = 'application/json';
        $resource = '/acls/'.$aclId;
        list($resp,$header) = $this->send("PUT",NULL,$body,$resource,$params,$headers);  
        return new UpdateACLResponse($header);
    }
    
    public function getACL(GetACLRequest $request){
        $params = array();
        $headers = array();

        $aclId = ($request->getAclId()!==null)?$request->getAclId():'';
        
        $resource = '/acls/'.$aclId;
        list($resp,$header) = $this->send("GET",NULL,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );

        return new GetACLResponse($resp,$header);
    }
    
    public function deleteACL(DeleteACLRequest $request){
        $params = array();
        $headers = array();
        $aclId = ($request->getAclId()!==null)?$request->getAclId():'';
        $resource = '/acls/'.$aclId;
        list($resp,$header) = $this->send("DELETE",NULL,NULL,$resource,$params,$headers); 
        return new DeleteACLResponse($header);
    }
    
    public function listACLs(ListACLsRequest $request){
        $params = array();
        $headers = array();
        if($request->getPrincipleId()!==null)$params['principleId'] = $request->getPrincipleId();
        if($request->getOffset()!==null)$params['offset'] = $request->getOffset();
        if($request->getSize()!==null)$params['size'] = $request->getSize();

        $resource = '/acls';
        list($resp,$header) = $this->send("GET",NULL,NULL,$resource,$params,$headers); 
        $requestId = isset ( $header ['x-log-requestid'] ) ? $header ['x-log-requestid'] : '';
        $resp = $this->parseToJson ( $resp, $requestId );
        return new ListACLsResponse($resp,$header);
    }

}

