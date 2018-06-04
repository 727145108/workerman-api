<?php

# @Author: crababy
# @Date:   2018-03-23T17:42:16+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-05-23T14:36:35+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License

namespace Crababy\Controller;

use Crababy\Library\Helper;


class Order extends Base {

  /**
   * 获取订单列表
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function index($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
      'state'     => '/^$|^待付款$|^待发货$|^待收货$|^已完成$|^已取消$/',
      'page'      => '/^\d+$/',
      'limit'     => '/^\d+$/',
    ], $request, $response);
    extract($request);
    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }
    $where = array();
    $where['member_id'] = $member['id'];
    if(!empty($state)) {
      $where['order_status'] = $state;
    }
    $stmt = $this->mysql->build($where, 'and ');
    $offset = ($page - 1) * $limit;
    $orders = $this->mysql->query("select id, order_sn, order_status, order_amount, shipping_amount, create_time from orders where {$stmt['sqlPrepare']} order by id desc limit ?, ? ", array_merge($stmt['bindParams'], [$offset, $limit]));
    if(false === $orders) {
      return -2;
    }
    foreach ($orders as $key => &$order) {
      $order_goods = $this->mysql->query("select good_title, spec_sn, spec_title, good_pic, good_unit, good_price, order_price, buy_num, refund_num, refund_money, promotion, state from orders_good where order_id = ?", [$order['id']]);
      if(false === $order_goods) {
        return -2;
      }
      $order['goods'] = $order_goods;
    }
    $response['data'] = ['items' => $orders];
    return 0;
  }

  /**
   * 获取订单详情
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function detail($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
      'order_id'     => '/^\d+$/'
    ], $request, $response);
    extract($request);
    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }
    //订单信息
    $order = $this->mysql->query("select id, order_sn, order_status, pay_status, send_status, refund_status, order_amount, good_amount, shipping_amount, tax_amount, coupon, coupon_amount, pay_title, realName, original_order_sn, create_time, update_time, send_time, confirm_time, over_time from orders where id = ? and member_id = ?", [$order_id, $member['id']], true);
    if(false === $order) {
      return 1401;
    }
    //订单商品信息
    $order_goods = $this->mysql->query("select good_title, spec_sn, spec_title, good_pic, good_unit, good_price, order_price, buy_num, refund_num, refund_money, promotion, state from orders_good where order_id = ?", [$order_id]);
    if(false === $order_goods) {
      return 1401;
    }
    //订单收货人信息
    $order_consignee = $this->mysql->query("select consignee, province, city, district, address, mobile, area_code from orders_consignee where order_id = ?", [$order_id], true);
    if(false === $order_consignee) {
      return 1401;
    }
    //订单支付信息
    $order_pay = $this->mysql->query("select pay_type, pay_time, pay_money, pay_serial_number, prepay_id, prepay_code, prepay_id_time, out_trade_no from orders_pay where order_id = ?", [$order_id], true);
    $response['data'] = ['order' => $order, 'order_goods' => $order_goods, 'order_consignee' => $order_consignee, 'order_pay' => $order_pay];
    return 0;
  }

  /**
   * 获取订单物流信息
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function logistics($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
      'order_id'     => '/^\d+$/'
    ], $request, $response);
    extract($request);
    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }
    //订单信息
    $order = $this->mysql->query("select id, order_sn, order_status, pay_status, send_status, refund_status, order_amount, good_amount, shipping_amount, tax_amount, coupon, coupon_amount, pay_title, realName, original_order_sn, create_time, update_time, send_time, confirm_time, over_time from orders where id = ? and member_id = ?", [$order_id, $member['id']], true);
    if(false === $order) {
      return 1401;
    }
    $logistics = $this->mysql->query("select order_id, com, title, invoice, is_check, data, addtime from orders_invoice where order_id = ?", [$order['id']], true);
    if(false === $logistics) {
      return 1404;
    }
    $logistics['data'] = json_decode($logistics['data']);
    $response['data'] = ['order' => $order, 'logistics' => $logistics];
    return 0;
  }
}
