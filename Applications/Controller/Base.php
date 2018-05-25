<?php

# @Author: crababy
# @Date:   2018-03-23T17:42:16+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-03-23T17:49:01+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License

namespace Crababy\Controller;

use Crababy\Core\Controller;
use Crababy\Library\Helper;

/**
 * index
 */
class Base extends Controller {



  /**
   * 验证会员是否登录
   * @param [type] $token [description]
   */
  public function ValidateLogin($token) {
    $memberId = Helper::decodeUserToken($token);
    $fields = ['id', 'token', 'nickname', 'avatar', 'email', 'mobile', 'level', 'point', 'pushClientId', 'deviceToken', 'state', 'registerTime', 'updateVersion'];
    //$this->redis->RedisCommands('del', 'user:'.$userId);
    $member = $this->redis->RedisCommands('hGetAll', 'member:'.$memberId);
    if (count($member) < count($fields)) {
      $fieldStmt = implode(',', $fields);
      $member = $this->mysql->query("select {$fieldStmt} from members where id = ? and token = ?", array($memberId, $token), true);
      if (false === $member) {
        return false;
      }
      $this->redis->RedisCommands('hMset', "member:{$member['id']}", $member);
      $this->redis->RedisCommands('setTimeout', "member:{$member['id']}", 3600);
    }
    //验证token是否过期
    if($token != $member['token']) {
      return false;
    }
    return $member;
  }

  /**
   * 清除会员缓存
   * @param  [type] $userId [description]
   * @return [type]         [description]
   */
  public function clearUserCache($memberId) {
    $this->redis->RedisCommands('del', 'member:'.$memberId);
    return true;
  }
}
