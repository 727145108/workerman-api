<?php

# @Author: crababy
# @Date:   2018-03-23T17:42:16+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-03-23T17:49:01+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License

namespace Crababy\Controller;

use Crababy\Library\Helper;

const APP_KEY = '24822447';

const APP_SECRET = '15ab71448878ce40e60fc52b5c563b47';

const API_URL = 'http://gw.api.taobao.com/router/rest';

const ADZONE_ID = '465494605';

/**
 * 淘宝开放平台
 */
class Index extends Base {
  /**
   * 好券清单API【导购】
   * @param  [type] $req  [description]
   * @param  [type] &$rsp [description]
   * @return [type]       [description]
   */
  public function taobaoList($req, &$rsp) {
  	$data = array(
  		'method'	 => 'taobao.tbk.dg.item.coupon.get',
  		'adzone_id'	=> '465494605',		//这里要网站的
      'page_size' => 100,
      'page_no'   => 1
  	);
    $this->mergeParams($data);
  	$tbGoodList = Helper::httpRequest(API_URL, $data);
  	$tbGoodList = json_decode($tbGoodList, true);
  	//"https://uland.taobao.com/coupon/edetail?e=1yIRzXBuYuAGQASttHIRqR6fJyDAdI5%2F2ElzakwnsL7B76UlhXoJ97SNY2R9OkKZ7GfjshihmILRoWCzhpsx14YYi%2FdMjOUym4VLH9mslwxxx5LHbihywNofRvYqFiPSJKCWJGW82IrWy6wkM21ptVhAmztsbMhPKYYH9EtgayBIH07HK3v5wIJH8T5oB9M7LEHYUk%2BOJuU%3D&traceId=0ab2013f15243882714155720e"
  	$items = $tbGoodList['tbk_dg_item_coupon_get_response']['results']['tbk_coupon'];
  	foreach ($items as $key => &$value) {
  		$coupon_click_url = $value['coupon_click_url'];
  		$params = parse_url($coupon_click_url, PHP_URL_QUERY);
  		$queryArr = explode("&", $params);
  		$coupon = explode("=", $queryArr[0]);
  		$value['coupon_me'] = $coupon[1];
  	}
  	$rsp['data'] = ['total' => $tbGoodList['tbk_dg_item_coupon_get_response']['total_results'], 'items' => $items];
  	return 0;
  }

  /**
   * 淘宝客商品详情（简版）
   * @param  [type] $req  [description]
   * @param  [type] &$rsp [description]
   * @return [type]       [description]
   */
  public function taobaoInfo($req, &$rsp) {
  	$data = array(
  		'method'	=> 'taobao.tbk.item.info.get',
  		'fields'	=> 'num_iid,title,pict_url,small_images,reserve_price,zk_final_price,user_type,provcity,item_url',		//这里要网站的
  		'num_iids'	=> $req['num_iid']
  	);
    $this->mergeParams($data);
  	$tbGoodInfo = Helper::httpRequest(API_URL, $data);
  	$tbGoodInfo = json_decode($tbGoodInfo, true);
  	$rsp['data'] = $tbGoodInfo['tbk_item_info_get_response']['results']['n_tbk_item'][0];
  	return 0;
  }

  /**
   * 关联推荐商品
   * @param  [type] $req  [description]
   * @param  [type] &$rsp [description]
   * @return [type]       [description]
   */
  public function recommendGood($req, &$rsp) {
  	$data = array(
  		'method'	=> 'taobao.tbk.item.recommend.get',
  		'fields'	=> 'num_iid,title,pict_url,small_images,reserve_price,zk_final_price,user_type,provcity,item_url',		//这里要网站的
  		'num_iid'	=> $req['num_iid']
  	);
    $this->mergeParams($data);
  	$tbRecommendGood = Helper::httpRequest(API_URL, $data);
  	$tbRecommendGood = json_decode($tbRecommendGood, true);
  	$rsp['data'] = []; // $tbRecommendGood['tbk_item_recommend_get_response']['results']['n_tbk_item'];
  	return 0;
  }

  /**
   * 阿里妈妈推广券信息查询
   * @param  [type] $req  [description]
   * @param  [type] &$rsp [description]
   * @return [type]       [description]
   */
  public function goodCoupon($req, &$rsp) {
    $data['method'] = 'taobao.tbk.coupon.get';
  	if(isset($req['me'])) {
  		$data['me'] = $req['me'];
  	} else {
  		$data['item_id'] = $req['item_id'];
  		$data['activity_id'] = $req['activity_id'];
  	}
    $this->mergeParams($data);
  	$tbGoodCoupon = Helper::httpRequest(API_URL, $data);
  	$tbGoodCoupon = json_decode($tbGoodCoupon, true);
  	$rsp['data'] = $tbGoodCoupon['tbk_coupon_get_response']['data'];
  	return 0;
  }

  public function tbkUrlConvert($req, &$rsp) {
    $data = array(
  		'method'	=> 'taobao.tbk.item.convert',
  		'fields'	=> 'num_iid,click_url',
  		'num_iids'	=> $req['num_iids'],
  		'adzone_id'	=> ADZONE_ID,
  	);
    $this->mergeParams($data);
  	$tbUrlConvert = Helper::httpRequest(API_URL, $data);
  	$tbUrlConvert = json_decode($tbUrlConvert, true);
  	$rsp['data'] = $tbUrlConvert['tbk_tpwd_item_convert_response']['results']['n_tbk_item'];
  	return 0;
  }

  /**
   * 生成淘口令
   * @param  [type] $req  [description]
   * @param  [type] &$rsp [description]
   * @return [type]       [description]
   */
  public function tbkSharePwdCreate($req, &$rsp) {
    $data = array(
  		'method'    => 'taobao.wireless.share.tpwd.create',
  		'tpwd_param'=> json_encode(array(
			'url'	      => $req['url'],
			'text'	    => '超值活动，惊喜活动多多'
  		)),
  	);
    $this->mergeParams($data);
  	$tbPwdInfo = Helper::httpRequest(API_URL, $data);
  	$tbPwdInfo = json_decode($tbPwdInfo, true);
  	$rsp['data'] = $tbPwdInfo['wireless_share_tpwd_create_response']['model'];
  	return 0;
  }

  /**
   * 淘宝客淘口令
   * @param  [type] $req  [description]
   * @param  [type] &$rsp [description]
   * @return [type]       [description]
   */
  public function tbkPwdCreate($req, &$rsp) {
    $data = array(
      'method'   => 'taobao.tbk.tpwd.create',
  		'url'      => $req['url'],
    	'text'     => '超值活动，惊喜活动多多',
  		'logo'     => $req['logo_url'],
  	);
    $this->mergeParams($data);
  	$tbPwdInfo = Helper::httpRequest(API_URL, $data);
  	$tbPwdInfo = json_decode($tbPwdInfo, true);
  	$rsp['data'] = $tbPwdInfo['tbk_tpwd_create_response']['data']['model'];
  	return 0;
  }

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
