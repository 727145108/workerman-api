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
    if($order['order_status'] != '待付款') {
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
    //先查找是否请求下单接口  prepay_id 放redis
    $app_id = $this->config['wxfwh']['app_id'];
    $redis_key = "prepayment-{$app_id}-{$order['order_sn']}";
    $order_payment = $this->redis->RedisCommands('hGetAll', $redis_key);
    if(false === $order_payment) {
      $nonce_str = Helper::generateRandomStr(12);
      $params = array(
        'appid'         => $app_id,
        'mch_id'        => $this->config['wxfwh']['mch_id'],
        'openid'        => $member['openId'],
        'nonce_str'     => $nonce_str,
        'sign_type'     => 'MD5',
        'body'          => '支付订单编号:' . $order['order_sn'],
        'out_trade_no'  => $order['order_sn'],
        'total_fee'     => $order['order_amount'],
        'spbill_create_ip'  => $_SERVER['REMOTE_ADDR'],
        'notify_url'    => $this->config['wxfwh']['notify_url'],
        'trade_type'    => 'JSAPI',
      );
      $params['sign'] = $this->wxMakeSign($params);
      $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
      $xml = Helper::xml_encode($params);
    	$result = Helper::httpRequest($url, $xml);
      $order_payment = Helper::xml_decode($result);
    	/*交易结果看result_code而不是return_code*/
    	if ('SUCCESS' != $order_payment['return_code']) {
    		return false;
    	}
    	/*验证签名*/
    	if (isset($order_payment['sign']) && $order_payment['sign'] != $this->wxMakeSign($order_payment)) {
    		//return false;
    	}
      $this->redis->RedisCommands('hset', $redis_key, array(
        'appid'       => $order_payment['appid'],
        'prepay_id'   => $order_payment['prepay_id'],
        'trade_type'  => $order_payment['trade_type'],
        'openid'      => $order_payment['openid']
      ));
      $this->redis->RedisCommands('Expire', $redis_key, 900);
    }
    $prePayParams = array(
  		'appId'     => $app_id,
  		'timeStamp' => time(),
  		'nonceStr'  => $nonce_str,
  		'package'   => "prepay_id={$order_payment['prepay_id']}",
  		'signType'  => 'MD5'
  	);
  	$prePayParams['paySign'] = $this->wxMakeSign($prePayParams);
  	return $prePayParams;
  }


  /**
   * 订单支付回调地址
   * @return [type] [description]
   */
  public function notify($request, &$response) {
    $response['type'] = 'xml';
    $data = $GLOBALS['HTTP_RAW_POST_DATA'];
    Helper::logger('WxNotifyIn:', $data);

    $notifyReply = array(
      'return_code' => 'FAIL',
      'return_msg'  => 'UNKOWN'
    );

    $notifyData = Helper::xml_decode($data);
    if(false === $notifyData || 'SUCCESS' != $notifyData['result_code']) {
      $response['data'] = Helper::xml_encode($notifyReply);
      return -1;
    }
    /*
    if($notifyData['sign'] != $this->wxMakeSign($notifyData)) {
      Helper::logger('WxNotifyIn:', 'Sign error');
      return Helper::xml_encode($notifyReply);
    }*/

    $out_trade_no = $notifyData['out_trade_no'];
    if(empty($out_trade_no)) {
      $response['data'] = Helper::xml_encode($notifyReply);
      return -1;
    }
    $pay_time = date('Y-m-d H:i:s', strtotime($notifyData['time_end']));
    //更新订单信息
    $ret = $this->mysql->query("update orders set order_status = ?, pay_status = ?, pay_time = ?, updateVersion = updateVersion + 1 where order_sn = ? and order_status = ? ", ['待发货', '已支付', $pay_time, $out_trade_no, '待付款']);
    if(false === $ret) {
      Helper::logger('WxNotifyIn:', 'update orders pay error');
      $response['data'] = Helper::xml_encode($notifyReply);
      return -1;
    }

    //保存支付流水
    $order_pay = array();
    $order_pay['pay_title'] = '微信支付';
    $order_pay['pay_time']  = $pay_time;
    $order_pay['pay_money'] = $notifyData['total_fee'];
    $order_pay['pay_serial_number'] = $notifyData['transaction_id'];
    $order_pay['out_trade_no'] = $notifyData['out_trade_no'];
    //$order_pay['bank_type'] = $notifyData['bank_type'];
    $stmt = $this->mysql->build($order_pay);
    $ret = $this->mysql->query("insert orders_pay set {$stmt['sqlPrepare']}", $stmt['bindParams']);
    if(false === $ret) {
      Helper::logger('WxNotifyIn:', 'insert orders pay error');
      $response['data'] = Helper::xml_encode($notifyReply);
      return -1;
    }
  	$notifyReply['return_code'] = 'SUCCESS';
  	$notifyReply['return_msg'] = 'OK';
    $response['data'] = Helper::xml_encode($notifyReply);
    return 0;
  }

  /**
   * 签名算法
   * @param  [type] $data [description]
   * @return [type]       [description]
   */
  private function wxMakeSign($data) {
  	//去除原签名字段
  	unset($data['sign']);
  	//生成签名 1按字典序排序字段 2拼接字段并加入key 3md5加密 4转换成大写
  	ksort($data);
  	return strtoupper(md5(urldecode(http_build_query($data)) . '&key=' . $this->config['wxfwh']['key']));
  }

}
