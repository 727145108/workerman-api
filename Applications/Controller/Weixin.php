<?php
# @Author: crababy
# @Date:   2018-04-08T14:30:22+08:00
# @Last modified by:   crababy
# @Last modified time: 2018-04-08T14:30:43+08:00
# @License: http://www.opensource.org/licenses/mit-license.php MIT License


namespace Crababy\Controller;

use Crababy\Library\Helper;

class Weixin extends Base {

  /**
   * 微信授权
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function wxAuthorize($request, &$response) {
    Helper::ValidateParams([
      'redirect_url'  => '/^(http|https):\/\/[A-Za-z0-9+&@#\/%?=~_|!:,.;]+[A-Za-z0-9+&@#\/%=~_|]+$/',
      'scope'  => '/^snsapi_(base|userinfo)$/',
      'state'  => '/^[_a-zA-Z0-9\-]+$/',
    ], $request, $response);
    $redirect_uri = urlencode($request['redirect_url']);
    $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->config['wxfwh']['app_id']}&redirect_uri={$redirect_uri}&response_type=code&scope={$request['scope']}&state={$request['state']}#wechat_redirect";

    $response['data'] = ['url' => $url];
    return 0;
  }

  /**
   * 微信服务号 获取access_token && 获取用户信息
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function wxfwhLogin($request, &$response) {
    Helper::ValidateParams([
      'code'  => '/^[_a-zA-Z0-9\-]+$/',
    ], $request, $response);
    $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->config['wxfwh']['app_id']}&secret={$this->config['wxfwh']['app_secret']}&code={$request['code']}&grant_type=authorization_code";

    $result = Helper::HttpRequest($url);
    $data = json_decode($result, true);
    if(isset($data['errcode'])) {
      $response['message'] = $data['errmsg'];
      return $data['errcode'];
    }
    //获取用户信息
    $member = $this->mysql->query("select id, nickname, token, state, mobile, email, avatar, level, point, updateVersion, openId, unionId from members where openId = ?", [$data['openid']], true);
    if(false === $member) {
      if('snsapi_userinfo' === $data['scope']) {
        $userinfo_url = "https://api.weixin.qq.com/sns/userinfo?access_token={$data['access_token']}&openid={$data['openid']}&lang=zh_CN";
        $userinfo = Helper::HttpRequest($userinfo_url);
        $userinfo = json_decode($userinfo, true);
        if(isset($userinfo['errcode'])) {
          $response['message'] = $userinfo['errmsg'];
          return $userinfo['errcode'];
        }
        $insertMember = array();
        $insertMember['state'] = '正常';
        $insertMember['token'] = '';
        $insertMember['nickname'] = $userinfo['nickname'];
        $insertMember['password'] = '';
        $insertMember['sex'] = $userinfo['nickname'] == 1 ? '男' : ($userinfo['nickname'] == 2 ? '女' : '未知');
        $insertMember['avatar'] = $userinfo['headimgurl'];
        $insertMember['mobile'] = '';
        $insertMember['email'] = '';
        $insertMember['level'] = 1;
        $insertMember['point'] = 0;
        $insertMember['pushClientId'] = '';
        $insertMember['deviceToken'] = '';
        $insertMember['openId'] = $userinfo['openid'];
        $insertMember['unionId'] = isset($userinfo['unionid']) ? $userinfo['unionid'] : '';
        $stmt = $this->mysql->build($insertMember);
        $ret = $this->mysql->query("insert members set {$stmt['sqlPrepare']}", $stmt['bindParams']);
        if(false === $ret) {
          return 1101;
        }
        $data['member_id'] = $ret;
      } else {
        //用户首次登录未写入信息，再次授权弹出信息框
        return 1102;
      }
    } else {
      $data['member_id'] = $member['id'];
    }
    $token = Helper::encodeUserToken($data['member_id'], 'member');
    //缓存access_token
    $ret = $this->mysql->query("update members set token = ? where id = ?", [$token, $data['member_id']]);
    if(false === $ret) {
      return -1;
    }
    $key = $data['openid'];
    $this->redis->RedisCommands('hMset', $key, $data);
    $this->redis->RedisCommands('Expire', $key, $data['expires_in']);
    $response['data'] = ['token' => $token];
    return 0;
  }

  /**
   * 获取微信全局token
   * @return [type] [description]
   */
  private function wxAccessToken() {
    $access_token_key = $this->config['wxfwh']['app_id'] . ':access_token';
    $access_token = $this->redis->RedisCommands('get', $access_token_key);
    if(false === $access_token) {
      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->config['wxfwh']['app_id']}&secret={$this->config['wxfwh']['app_secret']}";
      Helper::logger('access_token', "通过{$url}获取access_token");
      $data = Helper::HttpRequest($url);
      $data = json_decode($data, true);
      if(isset($data['errcode'])) {
        Helper::logger('access_token', $_data);
        return false;
      }
      Helper::logger('access_token', "获取access_token成功~");
      $this->redis->RedisCommands('set', $access_token_key, $data['access_token']);
      $this->redis->RedisCommands('Expire', $access_token_key, $data['expires_in']);
      $access_token = $data['access_token'];
    }
    return $access_token;
  }

  /**
   * 通过access_token获取jsapi_ticket
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  private function jsapiTicket() {
    $jsapi_ticket_key = $this->config['wxfwh']['app_id'] . ':jsapi_ticket';
    $jsapi_ticket = $this->redis->RedisCommands('get', $jsapi_ticket_key);
    if(false === $jsapi_ticket) {
      $access_token = $this->wxAccessToken();
      if(false === $access_token) {
        return false;
      }
      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$access_token}&type=jsapi";
      Helper::logger('jsapiTicket', "通过{$url}获取jsapi_ticket");
      $ticket = Helper::HttpRequest($url);
      $ticket = json_decode($ticket, true);
      if(isset($ticket['errcode']) && $ticket['errcode'] != 0) {
        Helper::logger('jsapiTicket', $ticket);
        return false;
      }
      Helper::logger('jsapiTicket', "获取jsapi_ticket成功~");
      $this->redis->RedisCommands('set', $jsapi_ticket_key, $ticket['ticket']);
      $this->redis->RedisCommands('Expire', $jsapi_ticket_key, $ticket['expires_in']);
      $jsapi_ticket = $ticket['ticket'];
    }
    return $jsapi_ticket;
  }

  /**
   * js-sdk 签名
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function jsSdkSignature($request, &$response) {
    $jsapi_ticket = $this->jsapiTicket();
    if(false === $jsapi_ticket) {
      return 1201;
    }
    $time = time();
    $nonceStr = Helper::generateRandomStr(8);
    $url = $request['url'];
    $_config = array(
      'debug'     => true,
      'appId'     => $this->config['wxfwh']['app_id'],
      'timestamp' => $time,
      'nonceStr'  => $nonceStr,
      'jsApiList' => $request['jsApiList']
    );
    $_data = array(
      'jsapi_ticket'  => $jsapi_ticket,
      'noncestr'      => $nonceStr,
      'timestamp'     => $time,
      'url'           => $url,
    );
    ksort($_data);
    $signStr = '';
    foreach ($_data as $key => $value) {
      $signStr .= $key . '=' . $value . '&';
    }
    $signStr = substr($signStr, 0, -1);
  	$_config['signature'] = sha1($signStr);

    $response['data'] = ['config' => $_config];
    return 0;
  }

  /**
   * 微信根据code获取openId等信息
   * @param  [type] $request  [description]
   * @param  [type] $response [description]
   * @return [type]           [description]
   */
  public function wxLogin($request, &$response) {
    Helper::ValidateParams([
      'code'  => '/^[_a-zA-Z0-9\-]+$/',
    ], $request, $response);
    $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->config['xcx']['app_id']}&secret={$this->config['xcx']['app_secret']}&js_code={$request['code']}&grant_type=authorization_code";
    $wxUserInfo = Helper::httpRequest($url, []);
    $wxUserInfo = json_decode($wxUserInfo, true);
    $authKey = md5(Helper::generateRandomStr(8));
    $this->redis->RedisCommands('hMset', $authKey, $wxUserInfo);
    $this->redis->RedisCommands('Expire', $authKey, 120);
    $response['data'] = ['authKey' => $authKey];
    return 0;
  }

  /**
   * 设置用户信息
   * @param [type] $request  [description]
   * @param [type] $response [description]
   */
  public function setUserInfo($request, &$response) {
    Helper::ValidateParams([
      'authKey'         => '/^[_a-zA-Z0-9]+$/',
      'encryptedData'   => '/^[_a-zA-Z0-9\-\/\+]+$/',
      'iv'              => '/^[_a-zA-Z0-9\-\/\+\=]+$/',
    ], $request, $response);
    //解密微信加密信息
    $wxUserInfo = $this->redis->RedisCommands('hGetAll', $request['authKey']);
    if(!isset($wxUserInfo['session_key']) || !isset($wxUserInfo['openid'])) {
      return -1;
    }
    $ret = $this->decryptData($request['encryptedData'], $request['iv'], $wxUserInfo['session_key'], $data);
    if($ret) {
      //写数据库 校验当前openId or unionid是否存在
      $member = $this->mysql->query("select id, nickname, token, state, mobile, email, avatar, level, point, updateVersion, openId, unionId from members where unionId = ?", [$data['unionId']], true);
      if(false === $member) {
        $insertMember = array();
        $insertMember['state'] = '正常';
        $insertMember['token'] = '';
        $insertMember['nickname'] = $data['nickName'];
        $insertMember['password'] = '';
        $insertMember['avatar'] = $data['avatarUrl'];
        $insertMember['mobile'] = '';
        $insertMember['email'] = '';
        $insertMember['level'] = 1;
        $insertMember['point'] = 0;
        $insertMember['pushClientId'] = '';
        $insertMember['deviceToken'] = '';
        $insertMember['openId'] = $data['openId'];
        $insertMember['unionId'] = $data['unionId'];
        $stmt = $this->mysql->build($insertMember);
        $ret = $this->mysql->query("insert members set {$stmt['sqlPrepare']}", $stmt['bindParams']);
        if(false === $ret) {
          return 1101;
        }
        $data['memberId'] = $ret;
      } else {
        $data['memberId'] = $member['id'];
      }
      $token = Helper::encodeUserToken($data['memberId'], 'MemberToken');
      $wxUserInfo = $this->redis->RedisCommands('del', $request['authKey']);
    }
    $response['data'] = [
      'memberId'  => $data['memberId'],
      'avatarUrl' => $data['avatarUrl'],
      'nickName'  => $data['nickName'],
    ];
    return 0;
  }

  /**
   * 解密微信数据信息
   * @param  [type] $encryptedData 加密的用户数据
   * @param  [type] $iv            与用户数据一同返回的初始向量
   * @param  [type] $data          解密后的原文
   * @return [type]                [description]
   */
  private function decryptData($encryptedData, $iv, $session_key, &$data) {
    if(strlen($session_key) != 24) {
      throw new \Exception("session_key is Illegal", -1001);
    }
    if(strlen($iv) != 24) {
      throw new \Exception("iv is Illegal", -1002);
    }
		$aesKey   = base64_decode($session_key);
    $aesIV    = base64_decode($iv);
    $aesCipher= base64_decode($encryptedData);
    $result   = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
    $data  = json_decode($result, true);
    if($data  == NULL) {
      throw new \Exception("iv is Illegal", -1003);
    }
    if($data['watermark']['appid'] != $this->config['xcx']['app_id']){
      throw new \Exception("app_id is Illegal", -1004);
		}
    return true;
  }


}
