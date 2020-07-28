<?php
namespace App\Sdk;
use App\Sdk\Traits\Log AS SdkLog;
use App\Sdk\Unit\Rsa2 AS RSA;

/**
 * SDK相关管理控制器 身份验证类
 *
 * @version  1.0.0
 * @author   Yunuo <ciwdream@gmail.com>
 */
class SdkAuth
{
    use SdkLog;

    private static $_ins = null;

    /**
     * 授权APPID
     *
     * @var string $appid
     */
    private static $appid;
    /**
     * 授权APPID 基础信息
     *
     * @var object $auth
     */
    private static $auth;

    /**
     * 获取SdkAuth实例
     *
     * @param string $appid  被授权APPID
     *
     * @return object
     */
    public static function getIns($appid) : object
    {
        self::$appid = $appid;
        // 对外开放一个接口，用于获取唯一实例
        if( !(self::$_ins instanceof self) ){
            self::$_ins = new self();
        }
        return self::$_ins;
    }

    /**
     * 验证授权Appid
     *
     * @return bool
     */
    public static function appidVerify() : bool
    {
        return self::$appid == self::$auth->appid;
    }

    /**
     * 验证授权游戏
     *
     * @return bool
     */
    public static function gameAuthVerify($gkey) : bool
    {
        if( 0 === self::$auth->is_inside )return true;
        $_models = new \App\Sdk\Models\SdkAuthGameModels();
        $_data = $_models->where('appid',self::$appid)->value('gamid');
        if( in_array($gkey, $_data) ){
            return true;
        }
        return false;
    }

    /**
     * 获取授权信息
     *
     * @return object
     */
    public static function getAuthInfo() : object
    {
        if(!self::$auth)return new class{};
        return self::$auth;
    }

    public static function setAuthInfo($name,$value = null) : void
    {
        self::$auth->{$name} = $value;
        return;
    }

    /**
     * Api传参Sign校验
     *
     * @param array $data
     *
     * @return bool
     */
    public static function verificationSign(array $data) : bool
    {
        if(empty($data['sign']) || !self::$auth)return false;
        $_data = array_filter($data);
        $_sign   = $data['sign'];
        unset($_data['sign']);
        ksort($data);
//        $_data['biz_content'] = rawurldecode($_data['biz_content']);
//        $_data['timestamp'] = rawurldecode($_data['timestamp']);
        $_rsaInfo = self::$auth->toArray();
        switch ($data['sign_type'])
        {
            case 'MD5':
                if( empty($_rsaInfo['md5_key']) )return false;
                $_str = http_build_query($_data,'',ini_get('arg_separator.output'),PHP_QUERY_RFC3986).'#'.$_rsaInfo['md5_key'];
                $_verifiMD5 = strtoupper(md5($_str));
                unset($_str);
                self::loggers('[SDK -> verificationSign]MD5:'.$_verifiMD5);
                return $_verifiMD5 == $_sign ? true : false;
                break;
            case 'RSA2':
                if( empty($_rsaInfo['rsa_private_key']) || empty($_rsaInfo['rsa_public_key']) )return false;
//                dump(Rsa::createSign(http_build_query($_data),$_rsaInfo['rsa_private_key']));
                RSA::setPublicKey($_rsaInfo['rsa_public_key']);
                RSA::setPrivateKey($_rsaInfo['rsa_private_key']);

                self::loggers('[SDK -> verificationSign]RSA2:'.RSA::createSign(http_build_query($_data)));
                return RSA::verifySign(http_build_query($_data),$_sign);
                break;
        }
        return false;
    }

    private function inits() : void
    {
        $this->auth();
    }

    private function auth() : void
    {
        self::$auth = null;
        $_models = new \App\Sdk\Models\SdkAuthKeyModels();
        $_data = $_models->where('appid',self::$appid)->first();
        if( $_data ){
            self::$auth = $_data;
        }
        return;
    }

    final private function __construct()
    {
        $this->inits();
    }

    final private function __clone(){}
}
