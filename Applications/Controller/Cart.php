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
      'token'     => '/^[a-zA-Z0-9|]{32}$/',
    ], $request, $response);
    extract($request);

    //校验商品库存 价格 运费 配送等
    foreach ($entities as $key => $item) {

    }

    return 0;
  }

}
