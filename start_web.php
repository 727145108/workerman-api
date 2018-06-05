<?php
# @Author: crababy
# @Date:   2018-04-04T09:29:12+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-04-04T09:29:23+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License


require_once 'vendor/autoload.php';

use Workerman\Worker;
use \Workerman\WebServer;
use \WorkerMan\Lib\Timer;
use Crababy\Core\Application;
use Crababy\Library\Helper;
use Crababy\Library\Mysql;
use Crababy\Library\RedisDb;

// 标记是全局启动
define('GLOBAL_START', 1);
// 心跳间隔50秒
define('HEARTBEAT_TIME', 60);

define('ROOT_PATH', __DIR__);

// WebServer
$worker = new Worker("http://0.0.0.0:55888");
$worker->name = 'BeierhuasuanApi';
$worker->count = 1;

$worker->onWorkerStart = function($worker) {

  $app = Application::getInstance();

  $app->setShared('config', function() {
    require_once ROOT_PATH . '/Applications/Config/Config.php';
    return $config;
  });

  $app->setShared('mysql', function() use ($app) {
    return new Mysql($app['config']['mysql']);
  });

  $app->setShared('redis', function() use ($app) {
    return new RedisDb($app['config']['redis']);
  });

  $app->setShared('lang', function() use ($app) {
    return $app['config']['language'];
  });

  Helper::$options = $app['config']['util'];

  if($worker->id === 0) {
    Timer::add(1, function() use ($worker) {
      $now_time = time();
      foreach ($worker->connections as $connection) {
        if(!isset($connection->lastMessageTime)) {
          $connection->lastMessageTime = $now_time;
          continue;
        }
        if($now_time - $connection->lastMessageTime > HEARTBEAT_TIME) {
          $connection->close();
        }
      }
    });
  }

  $worker->onConnect = function($connection) {

  };

  $worker->onMessage = function($connection, $data) use ($app) {
    $rsp['code'] = -1;
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (!preg_match("/application\/x-www-form-urlencoded/i", $content_type)) {
      $req = json_decode($GLOBALS['HTTP_RAW_POST_DATA'], TRUE);
    } else {
      $req = $_POST;
    }
    $app->run($req, $rsp);
    if(isset($rsp['type']) && 'xml' === $rsp['type']) {
      $connection->send($rsp['data']);
    } else {
      $connection->send(json_encode($rsp, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    }
  };

  $worker->onWorkerStop = function() {
    echo 'onWorkerStop';
  };
};

Worker::runAll();
