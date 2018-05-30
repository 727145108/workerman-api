<?php

# @Author: crababy
# @Date:   2018-03-23T17:42:16+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-05-29T16:17:40+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License

namespace Crababy\Controller;

use Crababy\Library\Helper;

class Address extends Base {

  public function getList($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
    ], $request, $response);
    extract($request);

    echo $token . "\n";

    $member = $this->ValidateLogin($token);
    if(false === $member) {
      echo "登录过期\n";
      return 1100;
    }

    $address_list = $this->mysql->query("select id, member_id, consignee, provice, city, district, address, mobile, area_code from members_consignee where member_id =? and state = ?", [$member['id'], '正常']);
    if(false === $address_list) {
      return 1103;
    }
    $response['data'] = ['items' => $address_list];
    return 0;
  }

}
