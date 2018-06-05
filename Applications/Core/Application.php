<?php

# @Author: crababy
# @Date:   2018-03-21T15:54:17+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-03-21T15:54:24+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License
#

namespace Crababy\Core;

use Crababy\Core\Container;
use \Workerman\Protocols\Http;
use Crababy\Library\Helper;

class Application extends Container  {

  private $language = 'zh';

  private static $_instance = null;

  public static function getInstance() {
    if(empty(self::$_instance)) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  private function __construct() {
    echo "实例化Application\n";
  }

  private function __clone() {}

  /**
   * 启动
   * @return [type] [description]
   */
  public function run($req, &$rsp) {
    try {
      $this->getLanguage();
      $this->check();
      $this->methodDispatch($req, $rsp);
    } catch (\Exception $ex) {
      $rsp['code'] = $ex->getCode();
      $rsp['desc'] = $ex->getMessage();
    }
    Helper::logger('Run:', $rsp);
    $this->formatMessage($rsp);
  }

  /**
   * 请求分发
   * @return [type] [description]
   */
  private function methodDispatch($req, &$rsp) {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? explode('?', $_SERVER['REQUEST_URI']) : explode('?', '/index/index');
    Helper::logger('methodDispatch', $request_uri);
    list(, $class, $method) = explode('/', $request_uri[0]);

    $controller = "\Crababy\Controller\\".ucfirst($class);

    if(!class_exists($controller) || !method_exists($controller, $method)) {
      throw new \Exception("Controller {$class} or Method {$method} is Not Exists", 1002);
    }

    //请求频率校验
    $this->checkRequestLimit($class, $method);

    $handler_instance = new $controller($this);
    $rsp['code'] = $handler_instance->$method($req, $rsp);
  }

  /**
   * 返回JSON数据
   * @param array $data [description]
   */
  private function formatMessage(&$response) {
    Http::header("Access-Control-Allow-Origin:*");
    Http::header("Access-Control-Allow-Method: POST, GET");
    Http::header("Access-Control-Allow-Headers: Origin, X-CSRF-Token, X-Requested-With, Content-Type, Accept");
    if(isset($response['type']) && 'xml' === $response['type']) {
      Http::header("Content-type: text/html;charset=utf-8");
    } else {
      Http::header("Content-type: application/json;charset=utf-8");
      $response['code'] = isset($response['code']) ? $response['code'] : 0;
      if(isset($response['message'])) {
        $response['desc'] = $response['message'];
        unset($response['message']);
      } else {
        $response['desc'] = isset($this['lang'][$this->language][$response['code']]) ? $this['lang'][$this->language][$response['code']] : "系统异常[{$response['code']}]";
      }
    }
    //Helper::logger('Response Result:', $response);
    //Http::end(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    //return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
  }


  /**
   * 访问频率限制
   * @return [type] [description]
   */
  private function checkRequestLimit($class, $method) {
    $clientIp = Helper::getClientIp();
    $apiLimitKey = "ApiLimit:{$class}:{$method}:{$clientIp}";
    $limitSecond = isset($this['config']['apiLimit'][$class][$method]['limitSecond']) ? $this['config']['apiLimit'][$class][$method]['limitSecond'] : 1;
    $limitCount = isset($this['config']['apiLimit'][$class][$method]['limitCount']) ? $this['config']['apiLimit'][$class][$method]['limitCount'] : 1000;
    $ret = $this['redis']->RedisCommands('get', $apiLimitKey);
    if (false === $ret) {
      $this['redis']->RedisCommands('setex', $apiLimitKey, $limitSecond, 1);
    } else {
      if($ret >= $limitCount) {
        $this['redis']->RedisCommands('expire', $apiLimitKey, 1);
        Helper::logger('checkRequestLimit:', "{$ret} Request Fast");
        throw new \Exception("Request faster", 1005);
      } else {
        $this['redis']->RedisCommands('incr', $apiLimitKey);
      }
    }
    return true;
  }

  /**
   * 校验请求方式
   * @return [type] [description]
   */
  private function check() {
    /*
    if(!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
      throw new \Exception("Error Request Method", -1);
    }
    */
    //校验数据完整性
  }

  /**
   * 设置语言类型
   * @return [type] [description]
   */
  private function getLanguage($default = 'zh') {
    if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
      $language = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
      if(in_array($language, array('zh', 'en'))) {
        $this->language = $language;
      }
    } else {
      $this->language = $default;
    }
  }


}
