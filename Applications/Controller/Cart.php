<?php

# @Author: crababy
# @Date:   2018-03-23T17:42:16+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-05-25T15:57:42+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License

namespace Crababy\Controller;

use Crababy\Library\Helper;

class Cart extends Base {

  /**
   * 检索购物车信息
   * 初步校验商品状态 是否可售等信息
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function checkout($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/'
    ], $request, $response);
    extract($request);

    if(!isset($entities)) {
      return 1004;
    }

    //校验商品库存 价格 运费 配送等
    $carts = [
      'goods' => [],
      'coupon' => [],
      'payments' => [], //支付方式 {default: 1, title: '微信支付', id: 1, description: '描述', promotion: []}
      'good_price' => 0,
      'freight' => 0, //运费
      'tax_price' => 0,
    ];
    foreach ($entities as $key => $item) {
      $good = $this->mysql->query("select goods.id, goods.good_title, goods.good_pic, goods.good_weight, goods.good_volume, goods.good_tax, goods.good_trade_type, goods.good_origin, goods.good_upc, goods.good_state, goods_spec.id as spec_id, goods_spec.spec_title, goods_spec.spec_sn, goods_spec.stock, goods_spec.price, goods_spec.state from goods, goods_spec where goods.id = goods_spec.good_id and goods_spec.good_id = ? and goods_spec.id = ?", [$item['good_id'], $item['spec_id']], true);
      if(false === $good) {
        return 1300;
      }
      if('正常' !== $good['good_state']|| '正常' !== $good['state']) {
        return 1301;
      }
      //检查库存信息
      if($item['buy_num'] > $good['stock']) {
        return 1302;
      }
      $this->redis->RedisCommands('set', "good:quantity:{$item['good_id']}:{$item['spec_id']}", $good['stock']);
      $good['buy_num'] = $item['buy_num'];
      $carts['goods'][$good['spec_id']] = $good;
      $carts['good_price'] += $good['price'] * $item['buy_num'];
      $carts['tax_price'] += $good['price'] * $item['buy_num'] * $good['good_tax'];
    }

    $carts['payments'] = [
      [
        'id' => 1, 'type' => 'wxpay', 'title' => '微信支付', 'description' => '微信快捷支付', 'default' => true, 'promotion' => []
      ],
      [
        'id' => 2, 'type' => 'alipay', 'title' => '支付宝', 'description' => '支付宝快捷支付', 'promotion' => []
      ]
    ];

    $response['data'] = $carts;
    return 0;
  }

  /**
   * 生成订单
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function orders($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
    ], $request, $response);
    extract($request);

    if(!isset($entities)) {
      return 1004;
    }

    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }
    //redis库存校验
    $ret = $this->validateStock($entities, $response);
    if(false === $ret) {
      return 1302;
    }
    $consignee = $this->mysql->query("select id, member_id, consignee, province, city, district, address, mobile, area_code from members_consignee where id =? and member_id = ?", [$consignee_id, $member['id']], true);
    if(false === $consignee) {
      return 1103;
    }
    $order = [
      'goods' => [],
      'order_amount' => 0,
      'good_amount' => 0,
      'tax_amount' => 0,
      'shipping_amount' => 0,
      'coupon_amount' => 0,
    ];
    //校验库存信息
    foreach ($entities as $key => $item) {
      //$quantity = $this->redis->RedisCommands('rpop', "good:quantity:{$item['good_id']}:{$item['spec_id']}");
      $good = $this->mysql->query("select goods.id, goods.good_title, goods.good_pic, goods.good_weight, goods.good_volume, goods.good_tax, goods.good_trade_type, goods.good_origin, goods.good_upc, goods.good_state, goods_spec.id as spec_id, goods_spec.spec_title, goods_spec.spec_sn, goods_spec.stock, goods_spec.price, goods_spec.state from goods, goods_spec where goods.id = goods_spec.good_id and goods_spec.good_id = ? and goods_spec.id = ?", [$item['good_id'], $item['spec_id']], true);
      if(false === $good) {
        return 1300;
      }
      if('正常' !== $good['good_state']|| '正常' !== $good['state']) {
        return 1301;
      }
      //检查库存信息
      /*
      if($item['buy_num'] > $good['stock']) {
        return 1302;
      }
      */
      $good['buy_num'] = $item['buy_num'];
      $order['goods'][$good['spec_id']] = $good;
      $order['good_amount'] += $good['price'] * $item['buy_num'];
      $order['tax_amount'] += $good['price'] * $item['buy_num'] * $good['good_tax'];
    }
    unset($entities);
    $order['order_amount'] = $order['good_amount'] + $order['tax_amount'] + $order['shipping_amount'] - $order['coupon_amount'];

    $this->mysql->beginTrans();
    $order_id = $this->generateOrder($order, $member, $payment);
    if(false === $order_id) {
      $this->mysql->rollBackTrans();
      return 1400;
    }

    $ret = $this->recordOrderGood($order_id, $order['goods']);
    if(false === $order_id) {
      $this->mysql->rollBackTrans();
      return 1400;
    }

    $ret = $this->recordConsignee($order_id, $consignee);
    if(false === $ret) {
      $this->mysql->rollBackTrans();
      return 1400;
    }
    $this->mysql->commitTrans();
    $response['data'] = ['order_id' => $order_id];
    return 0;
  }

  /**
   * 验证商品库存
   * @param  [type] $entities [description]
   * @return [type]           [description]
   */
  private function validateStock($entities, &$response) {
    foreach ($entities as $key => $item) {
      $result = $this->redis->RedisCommands('decrby', "good:quantity:{$item['good_id']}:{$item['spec_id']}", $item['buy_num']);
      if($result < 0) {
        $this->redis->RedisCommands('incrby', "good:quantity:{$item['good_id']}:{$item['spec_id']}", $item['buy_num']);
        $response['message'] = "商品{$item['good_title']}库存不足";
        return false;
      }
    }
    return true;
  }

  /**
   * 生成订单信息
   * @param  [type] $order   [description]
   * @param  [type] $member  [description]
   * @param  [type] $payment [description]
   * @return [type]          [description]
   */
  private function generateOrder($order, $member, $payment) {
    //开始生成订单
    $order_sn = Helper::generateRand('O');
    $orderInsert = array();
    $orderInsert['member_id'] = $member['id'];
    $orderInsert['order_sn'] = $order_sn;
    $orderInsert['order_amount'] = $order['order_amount'];
    $orderInsert['good_amount'] = $order['good_amount'];
    $orderInsert['shipping_amount'] = $order['shipping_amount'];
    $orderInsert['tax_amount'] = $order['tax_amount'];
    $orderInsert['coupon'] = '';
    $orderInsert['coupon_amount'] = $order['coupon_amount'];
    $orderInsert['pay_title'] = $payment['title'];
    $orderInsert['realName'] = '否';
    $orderInsert['original_order_sn'] = $order_sn;

    $stmt = $this->mysql->build($orderInsert);
    $ret = $this->mysql->query("insert orders set {$stmt['sqlPrepare']}", $stmt['bindParams']);
    return $ret;
  }

  /**
   * 记录购买商品记录
   * @param  [type] $order_id [description]
   * @param  [type] $goods    [description]
   * @return [type]           [description]
   */
  private function recordOrderGood($order_id, $goods) {
    foreach ($goods as $key => $good) {
      $orderGoodInsert = array();
      $orderGoodInsert['order_id'] = $order_id;
      $orderGoodInsert['good_title'] = $good['good_title'];
      $orderGoodInsert['spec_sn'] = $good['spec_sn'];
      $orderGoodInsert['spec_title'] = $good['spec_title'];
      $orderGoodInsert['good_pic'] = $good['good_pic'];
      $orderGoodInsert['good_unit'] = '件';
      $orderGoodInsert['good_price'] = $good['price'];
      $orderGoodInsert['order_price'] = $good['price'];
      $orderGoodInsert['good_total_price'] = $good['buy_num'] * $good['price'];
      $orderGoodInsert['buy_num'] = $good['buy_num'];
      $orderGoodInsert['promotion'] = '否';
      $stmt = $this->mysql->build($orderGoodInsert);
      $ret = $this->mysql->query("update goods_spec set stock = stock - ? where id = ?", [$good['buy_num'], $good['spec_id']]);
      if(false === $ret) {
        return false;
      }
      $ret = $this->mysql->query("insert orders_good set {$stmt['sqlPrepare']}", $stmt['bindParams']);
      if(false === $ret) {
        return false;
      }
    }
    return true;
  }

  /**
   * 记录购买收货人信息
   * @param  [type] $order_id  [description]
   * @param  [type] $consignee [description]
   * @return [type]            [description]
   */
  private function recordConsignee($order_id, $consignee) {
    $orderConsigneeInsert = array();
    $orderConsigneeInsert['order_id'] = $order_id;
    $orderConsigneeInsert['consignee'] = $consignee['consignee'];
    $orderConsigneeInsert['province'] = $consignee['province'];
    $orderConsigneeInsert['city'] = $consignee['city'];
    $orderConsigneeInsert['district'] = $consignee['district'];
    $orderConsigneeInsert['address'] = $consignee['address'];
    $orderConsigneeInsert['mobile'] = $consignee['mobile'];
    $orderConsigneeInsert['area_code'] = $consignee['area_code'];

    $stmt = $this->mysql->build($orderConsigneeInsert);
    $ret = $this->mysql->query("insert orders_consignee set {$stmt['sqlPrepare']}", $stmt['bindParams']);
    return $ret;
  }

  /**
   * 计算运费
   * @return [type] [description]
   */
  private function cacleFreight() {

  }

}
