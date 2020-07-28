<?php
namespace App\Sdk\Unit;
/**
 * RSA2 加密
 */
class Rsa2
{
    private static $PRIVATE_KEY = <<<EOD
-----BEGIN RSA PRIVATE KEY-----
////////
-----END RSA PRIVATE KEY-----
EOD;

    private static $PUBLIC_KEY  = <<<EOD
-----BEGIN PUBLIC KEY-----
\\\\\\\\
-----END PUBLIC KEY-----
EOD;

    /**
     * 获取私钥
     * @return bool|resource
     */
    public static function getPrivateKey()
    {
        $privKey = self::$PRIVATE_KEY;
        return openssl_pkey_get_private($privKey);
    }

    /**
     * 获取公钥
     * @return bool|resource
     */
    public static function getPublicKey()
    {
        $publicKey = self::$PUBLIC_KEY;
        return openssl_pkey_get_public($publicKey);
    }

    /**
     * 设置私钥
     *
     * @var $private_key 私钥
     *
     * @return void
     */
    public static function setPrivateKey($private_key) : void
    {
        self::$PRIVATE_KEY = $private_key;
        return;
    }

    /**
     * 设置公钥
     *
     * @var $public_key 公钥
     *
     * @return void
     */
    public static function setPublicKey($public_key) : void
    {
        self::$PUBLIC_KEY = $public_key;
        return;
    }

    /**
     * 创建签名
     * @param string $data 数据
     * @return null|string
     */
    public static function createSign($data = '')
    {
        if (!is_string($data)) {
            return null;
        }
        return openssl_sign(
            $data,
            $sign,
            self::getPrivateKey(),
            OPENSSL_ALGO_SHA256
        ) ? urlencode(base64_encode($sign)) : null;
    }

    /**
     * 验证签名
     * @param string $data 数据
     * @param string $sign 签名
     * @return bool
     */
    public static function verifySign($data = '', $sign = '') : bool
    {
        if (!is_string($data) || !is_string($sign)) {
            return false;
        }
        return (bool)openssl_verify(
            $data,
            base64_decode(urldecode($sign)),
            self::getPublicKey(),
            OPENSSL_ALGO_SHA256
        );
    }
}
