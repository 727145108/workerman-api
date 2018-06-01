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

    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }

    $address_list = $this->mysql->query("select id, member_id, consignee, province, city, district, address, mobile, area_code from members_consignee where member_id =? and state = ?", [$member['id'], '正常']);
    if(false === $address_list) {
      return 1103;
    }
    $response['data'] = ['items' => $address_list];
    return 0;
  }

  /**
   * 获取收货地址详情
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function info($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
      'consignee_id'  => '/^\d+$/',
    ], $request, $response);
    extract($request);

    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }
    $consignee = $this->mysql->query("select  id, member_id, consignee, province, city, district, address, mobile, area_code from members_consignee where id = ? and member_id = ? and state = ?", [$consignee_id, $member['id'], '正常'], true);
    if(false === $consignee) {
      return 1103;
    }
    $response['data'] = ['consignee' => $consignee];
    return 0;
  }

  /**
   * 新增地址
   * @param [type] $request  [description]
   * @param [type] $response [description]
   */
  public function add($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
    ], $request, $response);
    extract($request);

    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }
    $addressInsert = array();
    $addressInsert['member_id'] = $member['id'];
    $addressInsert['consignee'] = $consignee;
    $addressInsert['province'] = $province;
    $addressInsert['city'] = $city;
    $addressInsert['district'] = $district;
    $addressInsert['address'] = $address;
    $addressInsert['mobile'] = $mobile;
    $addressInsert['area_code'] = $area_code;
    $addressInsert['state'] = '正常';

    $stmt = $this->mysql->build($addressInsert);
    $ret = $this->mysql->query("insert members_consignee set {$stmt['sqlPrepare']}", $stmt['bindParams']);
    if(false === $ret) {
      return 1104;
    }
    return 0;
  }

  /**
   * 新增地址
   * @param [type] $request  [description]
   * @param [type] $response [description]
   */
  public function update($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
      'consignee_id'  => '/^\d+$/',
    ], $request, $response);
    extract($request);

    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }

    $addressUpdate = array();
    $addressUpdate['consignee'] = $consignee;
    $addressUpdate['province'] = $province;
    $addressUpdate['city'] = $city;
    $addressUpdate['district'] = $district;
    $addressUpdate['address'] = $address;
    $addressUpdate['mobile'] = $mobile;
    $addressUpdate['area_code'] = $area_code;
    $addressUpdate['state'] = '正常';

    $stmt = $this->mysql->build($addressUpdate);
    $ret = $this->mysql->query("update members_consignee set {$stmt['sqlPrepare']} where id = ? and member_id = ?", array_merge($stmt['bindParams'], [$consignee_id, $member['id']]));
    if(false === $ret) {
      return 1105;
    }
    return 0;
  }

  /**
   * 删除收货地址
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function delete($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
      'consignee_id'  => '/^\d+$/',
    ], $request, $response);
    extract($request);

    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }

    $ret = $this->mysql->query("update members_consignee set state = ? where id = ? and member_id = ?", ['禁用' ,$consignee_id, $member['id']]);
    if(false === $ret) {
      return 1106;
    }
    return 0;
  }

}
