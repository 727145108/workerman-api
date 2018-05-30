<?php

# @Author: crababy
# @Date:   2018-03-23T17:42:16+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-05-23T14:36:35+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License

namespace Crababy\Controller;

use Crababy\Library\Helper;


class Pay extends Base {

  /**
   *
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function prepay($request, &$response) {
    Helper::ValidateParams([
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
      'order_id'     => '/^\d+$/'
    ], $request, $response);
    extract($request);
    $member = $this->ValidateLogin($token);
    if(false === $member) {
      return 1100;
    }
    //获取订单信息
    $order = $this->mysql->query("select order_sn, order_status, pay_status, order_amount, pay_title, original_order_sn from orders where id = ? and member_id = ?", [$order_id, $member['id']], true);
    if(false === $order) {
      return 1401;
    }
    if($order['order_status'] != '正常' || $order['pay_status'] != '待支付') {
      return 1402;
    }
    switch ($order['pay_title']) {
      case '微信支付':
        $result = $this->wxfwhPay($order, $member);
        if(false === $result) {
          return 1403;
        }
        break;
      case '微信小程序':
        break;
      case '支付宝':
        break;
    }
    $response['data'] = ['prePay' => $result];
    return 0;
  }

  /**
   * 微信服务号
   * @param  [type] $order [description]
   * @return [type]        [description]
   */
  private function wxfwhPay($order, $member) {
    $params = array(
      'appid'         => $this->config['wxfwh']['app_id'],
      'mch_id'        => $this->config['wxfwh']['mch_id'],
      'openid'        => $member['openId'],
      'nonce_str'     => Helper::generateRandomStr(12),
      'sign_type'     => 'MD5',
      'body'          => '支付订单编号:' . $order['order_sn'],
      'out_trade_no'  => $order['order_sn'],
      'total_fee'     => $order['order_amount'],
      'spbill_create_ip'  => $_SERVER['REMOTE_ADDR'],
      'notify_url'    => '',
      'trade_type'    => 'JSAPI',
    );
    $params['sign'] = $this->wxMakeSign($params);
    $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    $xml = Helper::xml_encode($params);
  	$result = Helper::httpRequest($url, $xml);
    $result = Helper::xml_decode($result);
  	/*交易结果看result_code而不是return_code*/
  	if ('SUCCESS' != $result['return_code']) {
  		return false;
  	}
  	/*验证签名*/
  	if (isset($result['sign']) && $result['sign'] != $this->wxMakeSign($result)) {
  		return false;
  	}
    $prePayParams = array(
  		'appId'     => $result['appid'],
  		'timeStamp' => time(),
  		'nonceStr'  => $result['nonce_str'],
  		'package'   => "prepay_id={$result['prepay_id']}",
  		'signType'  => 'MD5'
  	);
  	$prePayParams['paySign'] = $this->wxMakeSign($prePayParams);
  	return $prePayParams;
  }

  private function wxMakeSign($data) {
  	//去除原签名字段
  	unset($data['sign']);
  	//生成签名 1按字典序排序字段 2拼接字段并加入key 3md5加密 4转换成大写
  	ksort($data);
  	return strtoupper(md5(urldecode(http_build_query($data)) . '&key=' . $this->config['wxfwh']['key']));
  }

}
