<?php
/**
 * 判断是否为空
 * 
 * @access  public
 * @version 0.0.1
 * @author  Yunuo <ciwdream@gmail.com>
 * 
 * @param mixed    $data    待检查的变量
 *  
 * @return bool
 */
function _empty($data)
{
    if( !is_array($data) ){
        try {
            $data = trim($data);
        } catch (\Think\Exception $ex) {
            $data = null;
        }
    }
    if( empty($data) ){
        return true;
    }else{
        return false;
    }
}