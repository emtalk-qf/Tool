<?php
/**
 * 字符截取(支持中文截取)
 * @param string $str     要截取的字符串 
 * @param int    $start   开始位置，默认从0开始
 * @param int    $length  截取长度 
 * @param string $charset 字符编码，默认UTF－8 
 * @param string $suffix  是否在截取后的字符后面显示省略号，默认true显示，false为不显示 
 * @return string 处理后的字符串
 */
function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = false) {
    if (function_exists("mb_substr")) {
        if ($suffix)
            return mb_substr($str, $start, $length, $charset) . "...";
        else
            return mb_substr($str, $start, $length, $charset);
    }elseif (function_exists('iconv_substr')) {
        if ($suffix)
            return iconv_substr($str, $start, $length, $charset) . "...";
        else
            return iconv_substr($str, $start, $length, $charset);
    }
    $re['utf-8'] = "/[x01-x7f]|[xc2-xdf][x80-xbf]|[xe0-xef][x80-xbf]{2}|[xf0-xff][x80-xbf]{3}/";
    $re['gb2312'] = "/[x01-x7f]|[xb0-xf7][xa0-xfe]/";
    $re['gbk'] = "/[x01-x7f]|[x81-xfe][x40-xfe]/";
    $re['big5'] = "/[x01-x7f]|[x81-xfe]([x40-x7e]|xa1-xfe])/";
    preg_match_all($re[$charset], $str, $match);
    $slice = join("", array_slice($match[0], $start, $length));
    if ($suffix)
        return $slice . "…";
    return $slice;
}