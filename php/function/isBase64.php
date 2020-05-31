<?php

/**
 *  判断数据是否为Base64编码数据
 * 
 * @param string $data 待处理的数据
 * 
 * @return bool
 */
function isBase64($data)
{
    $_allow = false;
    if( strpos($data,"\n") ){
        $data = str_replace("\n", '', $data);
    }
    if( strpos($data,"\r") ){
        $data = str_replace("\r", '', $data);
    }
    if( empty($data) || is_null($data) )return $_allow;
    try {
        $data = explode(',', $data);
        if( isset($data[1] ) ){
            if( $data[1] == base64_encode(base64_decode($data[1])) ){
                $_allow = true;
            }else{
                $_allow = false;
            }
        }else{
            if( $data[0] == base64_encode(base64_decode($data[0])) && strlen($data[0]) >= 32 ){
                $_allow = true;
            }else{
                $_allow = false;
            }
        }
    } catch (\Exception $ex) {
        $_allow = false;
    }
    return $_allow;
}