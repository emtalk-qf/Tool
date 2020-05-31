<?php
namespace App\Http\Sdk\Traits;
use App\Enum\ENUM_SDK_CODE AS E_CODE;
/**
 * SDK 错误处理类
 *
 * @version  1.0.0
 * @author   Yunuo <ciwdream@gmail.com>
 */
trait Handler
{
    public static $Handler = [];

    public static function setError($code,$sub_code='',$sub_msg='') : void
    {
        self::$Handler = [];
        self::$Handler['code'] = $code;

        if( !empty($sub_code) ){
            self::$Handler['sub_code'] = $sub_code;
        }

        if( !empty($sub_msg) ){
            self::$Handler['sub_msg'] = $sub_msg;
        }
        return;
    }

    public static function getError() : array
    {
        return self::$Handler;
    }

    public static function isError() : bool
    {
        return empty(self::$Handler) ? false : true;
    }

    public function _handler() : array
    {
        return self::getError();
    }
}
