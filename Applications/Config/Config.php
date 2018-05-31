<?php
# @Author: crababy
# @Date:   2018-03-25T09:23:52+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-03-25T09:24:54+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License


$config = [
  'debug'   => true,

  'xcx'     => [
    'app_id'      => 'wx5091eed8db67e750',
    'app_secret'  => '738db66388f61370c6da4c579e9a484a'
  ],

  'wxfwh'   => [
    'app_id'      => 'wx46ee0b166d8ac06b',
    'app_secret'  => 'b99aa4913f96997269ee3f6dcbb52a39',
    'mch_id'      => '1434786802',
    'key'         => '3e72281acf19f73aa4f22b0b310404f3',
    'notify_url'  => '',
  ],

  'util'     => [
    'record'      => true,
    'logPath'     => ROOT_PATH . '/logs/services/',
    'recordType'  => 'local',   //tcp local
    'address'     => 'tcp://118.24.17.199:55566',
  ],

  'mysql'   => [
    'host' => '127.0.0.1',
    'username' => 'root',
    'password' => '',
    'database_name' => 'crababy_shop'
  ],
  'redis' => [
    'host'    => '127.0.0.1',
    'port'    => '6379',
    'auth'    => '',
    'db'      => 2,
    'prefix'  => ''
  ],

  'language'  => [
    'zh'    => [
      -3   => '更新数据失败!',
      -2   => '数据获取失败!',
      -1   => '系统异常，请联系管理员',
      0    => '成功',
      1002 => '类或方法不存在!',

      1100	=> '登录已过期，请重新登录',
      1101  => '用户注册失败',
      1102  => '用户未注册',
      1103  => '获取收货地址失败~',

      1200  => '获取access_token失败~',
      1201  => '获取jsapi_ticket失败~',

      1300  => '获取商品信息失败~',
      1301  => '商品已下架~',
      1302  => '商品库存不足~',

      1400  => '生成订单失败~',
      1401  => '无效的订单',
      1402  => '订单状态有误',
      1403  => '支付异常,请稍后再试',
    ],
    'en'    => [
      0    => 'success',
    ],
  ],
];
