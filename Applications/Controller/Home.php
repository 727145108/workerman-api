<?php

# @Author: crababy
# @Date:   2018-03-23T17:42:16+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-05-23T14:36:35+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License

namespace Crababy\Controller;

use Crababy\Library\Helper;


class Home extends Base {

  /**
   * 首页数据信息
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function index($request, &$response) {
    Helper::ValidateParams([
    ], $request, $response);

    $response['data'] = [
      'images' => [
        'http://m.kjshg.com/Uploads/Picture/2017-09-08/59b20913433e5.jpg',
        'http://m.kjshg.com/Uploads/Picture/2017-03-15/58c8b29ee5cf1.jpg'
      ],
      'notice' => [
        'url' => 'http://127.0.0.1:8080/good/detail?id=1',
        'title' => 'notice 青意萱 青意宣 特大号落地紫砂陶瓷花盆红砂紫砂花盆 客厅花卉绿植粗砂花盆 口径19cm 大号'
      ]
    ];
    return 0;
  }

}
