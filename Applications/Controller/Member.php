<?php
# @Author: crababy
# @Date:   2018-04-08T14:30:22+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-04-08T14:30:43+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License


namespace Crababy\Controller;

use Crababy\Library\Helper;

class Member extends Base {

  /**
   * 会员注册
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function register($request, &$response) {
    Helper::ValidateParams([
      'username'  => '/^[_a-zA-Z0-9]+$/',
      'password'  => '/^[_a-zA-Z0-9]+$/',
      'mobile'    => '/^$|^1[0-9]{10}$/',
      'email'     => '/^$|^[a-zA-Z0-9_-]+@[a-zA-Z0-9]+(\.[a-z]+)+/',
      'avatar'    => '/^$|^[_a-zA-Z0-9]+$/',
      'pushClientId' => '/^$|^[0-9a-zA-Z]+$/',
      'deviceToken'  => '/^$|^[0-9a-zA-Z]+$/',
    ], $request, $response);
    return -1;
  }

  /**
   * 会员登录
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function login($request, &$response) {
    return -1;
  }

  /**
   * 获取会员信息
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function info($request, &$response) {
    Helper::ValidateParams([
      'token' => '/^[a-zA-Z0-9|]{32}$/'
    ], $request, $response);
    
  }

}
