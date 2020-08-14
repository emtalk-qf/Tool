<?php

namespace Traits;
use Unit\EnumUnit;

/**
 * Trait Enum
 *
 * @version  1.0.0
 * @author   Yunuo <ciwdream@gmail.com>
 */
trait Enum
{
    private static $_enumIns = [];

    /**
     * 枚举数据初始化
     *
     * @param string $fileName 枚举类名称
     * @param string $path     枚举类命名空间路径
     *
     * @return void
     */
    public static function enuminit($fileName,$path=null)
    {
        self::$_enumIns = EnumUnit::getIns($fileName);
        self::$_enumIns->setPath(empty($path)?'\App\Enum\\':$path);
    }

    /**
     * 获取枚举数据
     *
     * @param string $key      使用「.」符号从多维数组中检索值
     * @param mixed  $default  默认值，默认:null
     *
     * @return mixed
     */
    public static function enumkey($key,$default=null)
    {
        return self::$_enumIns->key($key,$default);
    }
}
