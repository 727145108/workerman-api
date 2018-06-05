<?php

# @Author: crababy
# @Date:   2018-03-23T17:42:16+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-05-23T14:36:27+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License

namespace Crababy\Controller;

use Crababy\Library\Helper;

class Good extends Base {

  /**
   * 获取商品列表
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function index($request, &$response) {
    Helper::ValidateParams([
      'page'  => '/^\d+$/',
      'limit' => '/^\d+$/',
      'q'  => '/^$|^[a-zA-Z0-9\x{4e00}-\x{9fa5}]+$/u',
    ], $request, $response);
    extract($request);
    $where = array();
    $where['good_state'] = '正常';
    if(isset($q)) {
      $where['good_title'] = ['like', "%{$q}%"];
    }
    $stmt = $this->mysql->build($where, 'and ');
    $offset = ($page - 1) * $limit;
    $goods = $this->mysql->query("select id, good_title, short_title, good_pic, good_tag, good_price, good_tax, good_trade_type from goods where {$stmt['sqlPrepare']} limit ?, ?", array_merge($stmt['bindParams'], [$offset, $limit]));
    if(false === $goods) {
      return -2;
    }
    foreach ($goods as $key => &$good) {
      $good['good_tag'] = explode(',', $good['good_tag']);
    }
    $response['data'] = ['items' => $goods];
    return 0;
  }

  /**
   * 获取商品详情
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function info($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
      'good_id'   => '/^\d+$/',
    ], $request, $response);
    extract($request);
    /*获取商品基础信息*/
    $good = $this->mysql->query("select  id, good_title, short_title, good_pic, good_tag, good_price, good_tax, good_trade_type from goods where id = ?", [$good_id], true);
    if(false === $good) {
      return 1300;
    }
    $good['good_tag'] = explode(',', $good['good_tag']);
    $good['good_pic'] = explode(',', $good['good_pic']);
    /*获取商品其他信息*/
    $good_extend = $this->mysql->query("select good_desc, good_detail, good_pack_list, good_attention, good_delivery_region from goods_extends where good_id = ?", [$good_id], true);
    if(false === $good_extend) {
      return 1300;
    }
    $good_extend['good_delivery_region'] = explode(',', $good_extend['good_delivery_region']);
    $good_spec = $this->mysql->query("select id, spec_title, stock, price from goods_spec where good_id = ?", [$good_id]);
    if(false === $good_spec) {
      return 1300;
    }
    $response['data'] = ['good' => $good, 'good_extend' => $good_extend, 'good_spec' => $good_spec];
    return 0;
  }

}
