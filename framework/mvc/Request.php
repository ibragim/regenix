<?php

namespace framework\mvc;

use framework\exceptions\CoreException;
use framework\lang\String;
use framework\net\URL;
use framework\utils\ArrayUtils;

class Request {

    const type = __CLASS__;

    protected $method = "GET";
    protected $uri    = "";
    protected $host   = "";
    protected $userAgent = "";
    protected $referer   = "";
    protected $port      = "";
    protected $protocol  = "http";
    
    protected $basePath = "";


    /**
     * @var URL
     */
    protected $currentUrl;

    public function __construct() {
        // TODO
    }
    
    public static function createFromGlobal(){
        
        $req = new Request();
        $req->setMethod($_SERVER['REQUEST_METHOD']);
        $req->setUri($_SERVER['REQUEST_URI']);
        $req->host = $_SERVER['HTTP_HOST'];
        $req->userAgent = $_SERVER['HTTP_USER_AGENT'];
        $req->referer   = $_SERVER['HTTP_REFERER'];
        $req->port      = (int)$_SERVER['SERVER_PORT'];
        $req->protocol  = 'http'; // TODO //$_SERVER['SERVER_PROTOCOL'];
        
        $req->currentUrl = URL::buildFromUri( $req->host, $req->uri, $req->protocol, $req->port );
        
        return $req;
    }
    
    /** 
     * @return string calc hash of request
     */
    public function getHash(){
        
        return sha1(
                $this->method. '|' . 
                $this->protocol . '|' . 
                $this->host . '|' . 
                $this->port . '|' . $this->uri );
    }

    /**
     * @return string
     */
    public function getUri(){
        return $this->uri;
    }
    
    /**
     * query from request uri
     * @return string
     */
    public function getQuery(){
        $tmp = explode('?', $this->uri, 2);
        return (string)$tmp[1];
    }
    
    /**
     * get request path
     * @return string
     */
    public function getPath(){
        $tmp = explode('?', $this->uri, 2);
        return (string)$tmp[0];
    }

        /**
     * @param string $url
     */
    public function setUri($url){
        
        $this->uri = $url;
        
        if ( $this->basePath ){
            $p = strpos($this->uri, $this->basePath);
            if ( $p === 0 )
                $this->uri = substr($this->uri, strlen($this->basePath));
            
            if ( !$this->uri )
                $this->uri = '/';
            else if ($this->uri[0] !== '/')
                $this->uri = '/' . $this->uri;
        }
    }

    /**
     * @return string
     */
    public function getHost(){
        return $this->host;
    }
    
    /**
     * @return string
     */
    public function getUserAgent(){
        return $this->userAgent;
    }
    
    /**
     * @return string
     */
    public function getReferer(){
        return $this->referer;
    }

    /**
     * @return string
     */
    public function getMethod(){
        return $this->method;
    }
    
    /**
     * @param string $method - get, post, etc.
     * @return \framework\mvc\Request
     */
    public function setMethod($method){
        $this->method = strtoupper($method);
        return $this;
    }

    public function setBasePath($path){
        
        $this->basePath = $path;
        $this->setUri($this->getUri());
        
        return $this;
    }

    /**
     * @param string $method - post, get, put, delete, etc.
     * @return boolean
     */
    public function isMethod($method){
        return $this->method === $method;
    }
    
    /**
     * 
     * @param array $methods
     * @return boolean
     */
    public function isMethods(array $methods){
        foreach ($methods as $value) {
            if ( $value === $this->method )
                return true;
        }
        return false;
    }
    
    /**
     * @param string|URL $url
     * @return boolean
     */
    public function isBase($baseUrl){
        
        if ( !($baseUrl instanceof URL) )
            $baseUrl = new URL($baseUrl);
        
        return $this->currentUrl->constaints($baseUrl);
    }

    private static $instance;
    
    /**
     * get current request
     * @return Request
     */
    public static function current(){
        
        if ( self::$instance )
            return self::$instance;
        
        return self::$instance = Request::createFromGlobal();
    }
}


class RequestQuery {
    
    /** @var Request */
    private $request;
    
    /** @var array */
    private $args = null;


    public function __construct() {
        $this->request = Request::current();
        $this->args = URL::parseQuery( $this->request->getQuery() );
    }
    
    /**
     * get all query arguments
     * @return array
     */
    public function getAll(){
        return $this->args;
    }

    /**
     * checks exists name arg
     * @param $name
     * @return bool
     */
    public function has($name){
        return isset($this->args[$name]);
    }

    /**
     * get one query argument
     * @param string $name
     * @param mixed $def
     * @return mixed
     */
    public function get($name, $def = null){
        $arg  = $this->args[$name];
        return $arg === null ? $def : $arg;
    }

    /**
     * get typed bind value
     * @param $name
     * @param $type
     * @param null $def
     * @return bool|float|RequestBindValue|int|string
     */
    public function getTyped($name, $type, $def = null){
        return RequestBinder::getValue($this->get($name, $def), $type);
    }
    
    /**
     * get integer typed query argument
     * @param string $name
     * @param integer $def
     * @return integer
     */
    public function getNumber($name, $def = 0){
        
        return (int)$this->get($name, (int)$def);
    }
    
    /**
     * get string typed query argument
     * @param string $name
     * @param string $def
     * @return string
     */
    public function getString($name, $def = ''){
        
        return (string)$this->get( $name, (string)$def );
    }
    
    
    /**
     * 
     * @param string $name
     * @param boolean $def
     * @return boolean
     */
    public function getBoolean($name, $def = false){
        
        return (boolean)$this->get($name, (boolean)$def);
    }
    
    /**
     * get array query argument
     * @param string $name
     * @param array $def
     * @return array
     */
    public function getArray($name, array $def = array()){
        
        $arg = $this->get($name, (array)$def);
        if (is_array( $arg ))
            return $arg;
        else
            return array($arg);
    }
    
    /**
     * get array typed from query string
     * @param string $name
     * @param string $type string|boolean|integer|double|array
     * @param array $def
     * @return array
     */
    public function getArrayTyped($name, $type = 'string', array $def = array()){
        $arg = $this->getArray($name, $def);
        foreach($arg as &$v){
            $v = RequestBinder::getValue($v, $type);
        }
        return $arg;
    }
    
    /**
     * get array from explode of query argument
     * @param string $name
     * @param string $delimiter
     * @param array $def
     * @return array
     */
    public function getExplode($name, $delimiter = ',', array $def = array()){
        $arg = $this->get($name, null);
        if ( $arg === null || is_array( $arg) )
            return (array)$def;
        
        return explode($delimiter, (string)$arg, 300);
    }
    
    /**
     * get array typed from explode of query argument
     * @param string $name
     * @param string $type
     * @param string $delimiter
     * @param array $def
     * @return array
     */
    public function getExplodeTyped($name, $type = 'string', $delimiter = ',', array $def = array()){
        
        $arg = $this->getExplode($name, $delimiter, $def);
        foreach($arg as &$v){
            $v = RequestBinder::getValue($v, $type);
        }
        return $arg;
    }
}


abstract class RequestBinder {

    /**
     * @param $value string
     * @param $type string
     */
    public static function getValue($value, $type){
        switch($type){
            case 'int':
            case 'integer':
            case 'long': {
                return (int)$value;
            } break;

            case 'double':
            case 'float': {
                return (double)$value;
            } break;

            case 'bool':
            case 'boolean': {
                return (boolean)$value;
            } break;

            case 'string':
            case 'str': {
                return (string)$value;
            }

            default: {
                $type = str_replace('.', '\\', $type);
                if ( class_exists($type) ){
                    $instance = new $type;
                    if ( $instance instanceof RequestBindValue ){
                        $instance->onBindValue($value);
                        return $instance;
                    } else
                        throw new BindValueInstanceException($type);
                } else
                    throw new BindValueException($value, $type);
            }
        }
    }
}

class BindValueException extends CoreException {
    public function __construct($value, $type){
        parent::__construct(String::format('Can\'t bind `%s` value as `%s` type', (string)$value, $type));
    }
}

class BindValueInstanceException extends CoreException {
    public function __construct($type){
        parent::__construct(String::format(
            'Bind error: `%s` class must be implements \framework\mvc\RequestBindValue interface for bind value', $type
        ));
    }
}

interface RequestBindValue {
    /**
     * @param $value string
     * @return null
     */
    public function onBindValue($value);
}

abstract class RequestBindParams {

    const type     = __CLASS__;

    static $method = 'GET';
    static $prefix = '';

    public function __construct(array $args, $prefix = ''){
        foreach($args as $key => $value){
            if ( $prefix ) {
                if (($p = strpos($key, $prefix)) === 0){
                    $key = substr($key, strlen($prefix));
                } else
                    continue;
            }

            if ( method_exists($this, 'set' . $key) ){
                $reflect = new \ReflectionMethod(get_class($this), 'set' . $key);
                $a       = array();
                foreach($reflect->getParameters() as $param){
                    if($class = $param->getClass()){
                        $a[] = RequestBinder::getValue($value, $class->getName());
                    } else
                        $a[] = $value;
                }
                $reflect->setAccessible(true);
                $reflect->invokeArgs($this, $a);
            } else if ( property_exists($this, $key) ) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * @param null|string $prefix - if null default used
     * @param null $method
     * @param null|string $method - if null default used
     * @return RequestBindParams
     */
    public static function current($prefix = null){
        $class   = get_called_class();
        $_method = strtoupper($class::method);
        switch($_method){
            case 'POST': {
                $httpArgs = $_POST;
            } break;
            case 'GET': {
                $tmp = new RequestQuery();
                $httpArgs = $tmp->getAll();
            } break;
            case 'REQUEST': {
                $httpArgs = $_REQUEST;
            } break;
            case 'COOKIE': {
                $httpArgs = $_COOKIE;
            } break;
            case 'SESSION': {
                $httpArgs = $_SESSION;
            } break;
            case 'FILES': {
                $httpArgs = $_FILES;
            } break;
        }
        return new $class( $httpArgs, $prefix ? $prefix : $class::prefix );
    }
}