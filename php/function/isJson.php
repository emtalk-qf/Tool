<?php
/**
  *  判断数据是否为JSON数据
  * 
  * @param string $data 待处理的数据
  * 
  * @return bool
  */
function isJson($data) 
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