<?php
namespace App\Sdk\Unit;


class Fun
{

    /**
     *  判断数据是否为JSON数据
     *
     * @param string $data 待处理的数据
     *
     * @return bool
     */
    public static function isJson($data) : bool
    {
        if (empty($data))return false;
        try {
            json_decode($data, true);
            if (json_last_error() <= 0) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    public static function getEnum($enumName)
    {
        list($file,$field) = explode('.',$enumName);
        $_enumClass = '\App\Sdk\Enum\\'.$file;
        try {
            $_test = new $_enumClass($field);
        }catch (\Exception $exception){
            return [20000,'服务不可用'];
        }
        return $_test->getValue();
    }

}
