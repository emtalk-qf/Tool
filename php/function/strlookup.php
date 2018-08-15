<?php
/**
 * 字符串查找
 * 
 * @access  public
 * @version 0.0.1
 * @author  Yunuo <ciwdream@gmail.com>
 * 
 * @param string  $haystack  在该字符串中进行查找
 * @param mixed   $needle    needle仅支持字符串或一维索引数组类型。
 *  
 * @return bool
 */
function strlookup($haystack, $needle)
{
    if( is_array($needle) ){
        foreach ($needle as $value) {
            if (strstr($haystack, $value) !== false) {
                return true;
            }
        }
        unset($value);
    }else{
        return strstr($haystack, $needle) ? true : false;
    }
    return false;
}
