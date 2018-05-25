<?php
# @Author: crababy
# @Date:   2018-04-08T14:30:22+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-04-24T08:58:37+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License


namespace Crababy\Controller;

use Crababy\Library\Helper;


const APP_KEY = '24822447';

const APP_SECRET = '15ab71448878ce40e60fc52b5c563b47';

const API_URL = 'http://gw.api.taobao.com/router/rest';

const ADZONE_ID = '465494605';


class Tbk extends Base {

  /**
   * 通用物料搜索API（导购）
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function getProductList($request, &$response) {
    //taobao.tbk.dg.material.optional
    $data = array(
      'method'    => 'taobao.tbk.dg.material.optional',
      'adzone_id'	=> ADZONE_ID,		//这里要网站的
      'platform'  => 2,
      'page_size' => 20,
      'sort'      => 'total_sales',
    );
    $data['has_coupon'] = isset($request['has_coupon']) ? $request['has_coupon'] : 'false';
    $data['page_no'] = isset($request['page_no']) ? $request['page_no'] : 1;
    if(isset($request['q']) && !empty($request['q'])) {
      $data['q'] = $request['q'];
    }
    $this->mergeParams($data);
    $tbkProductList = Helper::httpRequest(API_URL, $data);
    $tbkProductList = json_decode($tbkProductList, true);
    if(isset($tbkProductList['error_response'])) {
      $response['message'] = isset($tbkProductList['error_response']['sub_msg']) ? $tbkProductList['error_response']['sub_msg'] : $tbkProductList['error_response']['msg'];
      return $tbkProductList['error_response']['code'];
    }
    $results = $tbkProductList['tbk_dg_material_optional_response']['result_list']['map_data'];
    $productList = array();
    //写入数据库
    foreach ($results as $k => $item) {
      //查询优惠券详细信息
      $couponData = $this->getCouponByCouponId($item['num_iid'], $item['coupon_id']);
      if(false != $couponData) {
        $item['coupon_start_fee'] = $couponData['coupon_start_fee'];
        $item['coupon_amount'] = $couponData['coupon_amount'];
      }
      /*
      if(isset($request['record']) && true == $request['record']) {
        $this->recordDatabase($request['q'], $item, $couponData);
      }*/
      $product = $this->recordDatabase($request['q'], $item, $couponData);
      $productList[$k]['id'] = $product['id'];
      $productList[$k]['product_title'] = $item['title'];
      $productList[$k]['product_main_image'] = $item['pict_url'];
      $productList[$k]['product_id'] = $item['num_iid'];
      $productList[$k]['zk_final_price'] = $item['zk_final_price'];
      $productList[$k]['shop_name'] = $item['shop_title'];
      $productList[$k]['coupon_id'] = $item['coupon_id'];
      if(false != $couponData) {
        $productList[$k]['coupon_start_fee'] = $couponData['coupon_start_fee'];
        $productList[$k]['coupon_amount'] = $couponData['coupon_amount'];
      }
      if($item['zk_final_price'] >= $couponData['coupon_start_fee']) {
        $productList[$k]['final_price'] = number_format($item['zk_final_price'] - $couponData['coupon_amount'], 2);
      } else {
        $productList[$k]['final_price'] = $item['zk_final_price'];
      }
      $productList[$k]['rob_coupon'] = $item['coupon_total_count'] - $item['coupon_remain_count'];
    }
    if(!isset($request['record']) || false === $request['record']) {
      $response['data'] = ['items' => $productList, 'total' => sizeof($results)];
    }
    return 0;
  }

  /**
   * 写入数据缓存
   * @param  [type] $item       [description]
   * @param  [type] $couponData [description]
   * @return [type]             [description]
   */
  private function recordDatabase($q, $item, $couponData) {
    //查询商品是否存在
    $product = $this->mysql->query("select id from discount_products where product_id = ?", [$item['num_iid']], true);
    if(false === $product) {
      $insertProduct = array();
      $insertProduct['type'] = $item['user_type'] === 0 ? '淘宝' : '天猫';
      $insertProduct['q'] = $q;
      $insertProduct['product_title'] = $item['title'];
      $insertProduct['product_main_image'] = $item['pict_url'];
      $insertProduct['product_id'] = $item['num_iid'];
      $insertProduct['product_price'] = $item['reserve_price'];
      $insertProduct['zk_final_price'] = $item['zk_final_price'];
      $insertProduct['product_url'] = $item['item_url'];
      $insertProduct['shop_name'] = $item['shop_title'];
      $insertProduct['coupon_id'] = $item['coupon_id'];
      $insertProduct['discount_url'] = '';
      $insertProduct['coupon_url'] = '';
      $insertProduct['coupon_info'] = $item['coupon_info'];
      $insertProduct['coupon_count'] = $item['coupon_total_count'];
      $insertProduct['coupon_surplus'] = $item['coupon_remain_count'];
      $insertProduct['small_images'] = isset($item['small_images']) ? json_encode($item['small_images']['string']) : json_encode(array($item['pict_url']));
      $insertProduct['provcity'] = $item['provcity'];
      $insertProduct['commission_rate'] = $item['commission_rate'];
      $insertProduct['volume'] = $item['volume'];
      $insertProduct['seller_id'] = $item['seller_id'];
      if(isset($item['coupon_start_time'])) {
        $insertProduct['coupon_start_time'] = $item['coupon_start_time'];
      }
      if(isset($item['coupon_end_time'])) {
        $insertProduct['coupon_end_time'] = $item['coupon_end_time'];
      }
      if(false != $couponData) {
        $updateProduct['coupon_start_fee'] = $couponData['coupon_start_fee'];
        $updateProduct['coupon_amount'] = $couponData['coupon_amount'];
      }

      $stmt = $this->mysql->build($insertProduct);
      $ret = $this->mysql->query("insert discount_products set {$stmt['sqlPrepare']}", $stmt['bindParams']);
      if(false == $ret) {
        Helper::logger("Insert Product Error: ", $item['title']);
      }
      $product['id'] = $ret;
    } else {
      $updateProduct = array();
      $updateProduct['product_main_image'] = $item['pict_url'];
      $updateProduct['product_price'] = $item['reserve_price'];
      $updateProduct['zk_final_price'] = $item['zk_final_price'];
      $updateProduct['coupon_id'] = $item['coupon_id'];
      $updateProduct['coupon_info'] = $item['coupon_info'];
      $updateProduct['coupon_count'] = $item['coupon_total_count'];
      $updateProduct['coupon_surplus'] = $item['coupon_remain_count'];
      $updateProduct['small_images'] = isset($item['small_images']) ? json_encode($item['small_images']['string']) : json_encode(array($item['pict_url']));
      $updateProduct['commission_rate'] = $item['commission_rate'];
      $updateProduct['volume'] = $item['volume'];
      if(isset($item['coupon_start_time'])) {
        $updateProduct['coupon_start_time'] = $item['coupon_start_time'];
      }
      if(isset($item['coupon_end_time'])) {
        $updateProduct['coupon_end_time'] = $item['coupon_end_time'];
      }

      if(false != $couponData) {
        $updateProduct['coupon_start_fee'] = $couponData['coupon_start_fee'];
        $updateProduct['coupon_amount'] = $couponData['coupon_amount'];
      }

      $stmt = $this->mysql->build($updateProduct);
      $ret = $this->mysql->query("update discount_products set {$stmt['sqlPrepare']} where id = ? ", array_merge($stmt['bindParams'], [$product['id']]));
      if(false == $ret) {
        Helper::logger("Update Product Error: ", $item['title']);
      }
    }
    return $product;
  }

  /**
   * 阿里妈妈推广券信息查询
   * 根据商品Id & 优惠券ID获取优惠信息
   * @param  [type] $item_id     [description]
   * @param  [type] $activity_id [description]
   * @return [type]              [description]
   */
  private function getCouponByCouponId($item_id, $activity_id) {
    if(empty($item_id) || empty($activity_id)) {
      return false;
    }
    $data = array(
      'method'        => 'taobao.tbk.coupon.get',
      'item_id'       => $item_id,
      'activity_id'   => $activity_id,
    );
    $this->mergeParams($data);
    $tbGoodCoupon = Helper::httpRequest(API_URL, $data);
    $tbGoodCoupon = json_decode($tbGoodCoupon, true);
    return $tbGoodCoupon['tbk_coupon_get_response']['data'];
  }

  /**
   * 淘宝客商品链接转换
   * [tbkUrlConvert description]
   * @param  [type] $req [description]
   * @param  [type] $rsp [description]
   * @return [type]      [description]
   */
  public function tbkUrlConvert($request, &$response) {
    $data = array(
  		'method'	=> 'taobao.tbk.item.convert',
  		'fields'	=> 'num_iid,click_url',
  		'num_iids'	=> $request['itemId'],
  		'adzone_id'	=> ADZONE_ID,
  	);
    $this->mergeParams($data);
  	$tbUrlConvert = Helper::httpRequest(API_URL, $data);
  	$tbUrlConvert = json_decode($tbUrlConvert, true);
    print_r($tbUrlConvert);
  	$response['data'] = $tbUrlConvert['tbk_tpwd_item_convert_response']['results']['n_tbk_item'];
  	return 0;
  }

  /**
   * 生成淘口令
   * 二合一版 https://uland.taobao.com/coupon/edetail?itemId=?&activityId=?
   * 优惠券链接 http://shop.m.taobao.com/shop/coupon.htm?seller_id= ？ &activity_id= ？
   * @param  [type] $request  [description]
   * @param  [type] &$response [description]
   * @return [type]       [description]
   */
  public function tbkPwdCreate($request, &$response) {
    Helper::ValidateParams([
      'itemId'  => '/^\d+$/'
    ], $request, $response);
    if(!isset($request['activityId']) || empty($request['activityId'])) {
      $url = "https://uland.taobao.com/coupon/edetail?itemId={$request['itemId']}&activityId={$request['activityId']}";
    } else {
      $url = "https://uland.taobao.com/coupon/edetail?itemId={$request['itemId']}&activityId={$request['activityId']}";
    }
    $data = array(
  		'method'    => 'taobao.tbk.tpwd.create',
			'url'	      => $url,
			'text'	    => '超值活动，惊喜活动多多',
  		'logo'      => $request['logo_url'],
  	);
    $this->mergeParams($data);
    print_r($data);
  	$tbPwdInfo = Helper::httpRequest(API_URL, $data);
  	$tbPwdInfo = json_decode($tbPwdInfo, true);
    if(isset($tbPwdInfo['error_response'])) {
      $response['message'] = $tbPwdInfo['error_response']['msg'];
      return $tbPwdInfo['error_response']['code'];
    }
  	$response['data'] = $tbPwdInfo['tbk_tpwd_create_response']['data']['model'];
  	return 0;
  }

  /**
   * 合并请求参数
   * @param  array  $data [description]
   * @return [type]       [description]
   */
  private function mergeParams(array &$data) {
    $baseParams = array(
      'app_key'	    => APP_KEY,
      'sign_method'	=> 'md5',
      'timestamp'	  => date("Y-m-d H:i:s"),
      'format'	    => 'json',
      'v'			      => '2.0',
    );
    $data = array_merge($baseParams, $data);
  	$data['sign'] = $this->GenerateSign($data);
  }



  /**
   * 生成签名
   * @param [type] $data       [description]
   * @param [type] $app_secret [description]
   */
  private function GenerateSign(array $data) {
  	ksort($data);
  	$signStr = '';
  	foreach ($data as $key => $value) {
  		$signStr .= $key . $value;
  	}
  	$sign = strtoupper(bin2hex(md5(APP_SECRET . $signStr . APP_SECRET, true)));
  	return $sign;
  }
}
