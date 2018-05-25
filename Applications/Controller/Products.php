<?php
# @Author: crababy
# @Date:   2018-04-08T14:30:22+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-04-08T14:30:43+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License


namespace Crababy\Controller;

use Crababy\Library\Helper;

class Products extends Base {

  /**
   * 获取商品列表
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function getList($request, &$response) {
    Helper::ValidateParams([
      'page'  => '/^\d+$/',
      'limit' => '/^\d+$/',
      'type'  => '/^$|^q/',
      'value' => '/^$|^[a-zA-Z0-9\x{4e00}-\x{9fa5}]+$/u',   //匹配汉字
    ], $request, $response);
    $where = array();
    $where['1'] = 1;
    if(isset($request['type']) && isset($request['value'])) {
		  $where[$request['type']] = $request['value'];
    }
    $stmt = $this->mysql->build($where, 'and ');
    $total = $this->mysql->query("select count(*) as total from discount_products where {$stmt['sqlPrepare']}", $stmt['bindParams'], true);
    $offset = ($request['page'] - 1) * $request['limit'];
    $goods = $this->mysql->query("select id, type, q, product_title, product_main_image, product_id, product_url, product_price, zk_final_price, provcity, shop_name, volume, coupon_info, coupon_id, coupon_start_fee, coupon_amount, coupon_count, coupon_surplus, coupon_start_time, coupon_end_time from discount_products where {$stmt['sqlPrepare']} order by coupon_amount desc limit ?, ?", array_merge($stmt['bindParams'], [$offset, $request['limit']]));
    if(false === $goods) {
      return -2;
    }
    foreach ($goods as &$good) {
      if($good['zk_final_price'] >= $good['coupon_start_fee']) {
        $good['final_price'] = number_format($good['zk_final_price'] - $good['coupon_amount'], 2);
      } else {
        $good['final_price'] = $good['zk_final_price'];
      }
      $good['rob_coupon'] = $good['coupon_count'] - $good['coupon_surplus'];
    }
    $response['data'] = ['items' => $goods, 'total' => $total['total']];
    return 0;
  }

  /**
   * 获取商品详情
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function getGoodInfo($request, &$response) {
    Helper::ValidateParams([
      'goodId'  => '/^\d+$/'
    ], $request, $response);
    $product = $this->mysql->query("select  id, type, q, product_title, product_main_image, product_url, small_images, product_id, product_price, zk_final_price, provcity, shop_name, volume, coupon_info, coupon_id, coupon_start_fee, coupon_amount, coupon_count, coupon_surplus, coupon_start_time, coupon_end_time from discount_products where id = ?", [$request['goodId']], true);
    if(false === $product) {
      return -2;
    }
    $product['detail'] = $product['small_images'] = json_decode($product['small_images']);
    if($product['zk_final_price'] >= $product['coupon_start_fee']) {
      $product['final_price'] = number_format($product['zk_final_price'] - $product['coupon_amount'], 2);
    } else {
      $product['final_price'] = $product['zk_final_price'];
    }
    $product['rob_coupon'] = $product['coupon_count'] - $product['coupon_surplus'];
    $product['_config'] = array(
      'showCoupon' => false,
      'btnText' =>'收藏',
      'showDetail' => true,
      'showHowToUse' => true,
      'showRecommend' => true,
    );
    $response['data'] = $product;
    return 0;
  }

  /**
   * 获取推荐商品信息
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function getRecommendsProduct($request, &$response) {
    Helper::ValidateParams([
      'q'  => '/^$|^[a-zA-Z0-9\x{4e00}-\x{9fa5}]+$/u'
    ], $request, $response);
    $products = $this->mysql->query("select  id, type, q, product_title, product_main_image, product_url, small_images, product_id, product_price, zk_final_price, provcity, shop_name, volume, coupon_info, coupon_id, coupon_start_fee, coupon_amount, coupon_count, coupon_surplus, coupon_start_time, coupon_end_time from discount_products where q = ? order by coupon_amount desc limit 10", [$request['q']]);
    if(false === $products) {
      return -2;
    }
    foreach ($products as &$good) {
      if($good['zk_final_price'] >= $good['coupon_start_fee']) {
        $good['final_price'] = number_format($good['zk_final_price'] - $good['coupon_amount'], 2);
      } else {
        $good['final_price'] = $good['zk_final_price'];
      }
      $good['rob_coupon'] = $good['coupon_count'] - $good['coupon_surplus'];
    }
    $response['data'] = ['items' => $products];
    return 0;
  }
}
