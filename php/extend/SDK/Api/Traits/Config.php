<?php
namespace App\Sdk\Traits;

trait Config
{

    private static $_config = [];

    public static function setConfigs($key, $value=null) : void
    {
        static::$_config = array_merge(static::$_config,['sdk_'.$key=>$value]);
        config([
            'sdk_'.$key=>$value
        ]);
    }

    public static function getConfigs($key = null, $default = null)
    {
        if( is_null($key) ){
            return self::$_config;
        }
        return config('sdk_'.$key, $default);
    }
}
