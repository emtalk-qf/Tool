<?php
/**
 * 格式化HTML数据
 * 
 * @version 0.0.1
 * @author  Yunuo <ciwdream@gmail.com>
 * 
 * @param string  $str  待处理的HTML标签字符串
 *  
 * @return string
 */
function htmlFormat($str)
{
    // 设置要保留的标签
    $_retainLabel = [
        '<img>',
        '<p>',
        '<span>',
        '<video>',
        '<br>',
        '<hr>',
        '<table>',
        '<thead>',
        '<tfoot>',
        '<tbody>',
        '<tr>',
        '<td>'
    ];
    $str = trim(strip_tags($str,implode(' ', $_retainLabel)));
    // 去除HTML标签属性
    foreach ($_retainLabel AS $_labelName){
        $_labelName = substr($_labelName,1,-1);
        preg_match_all('/<'.$_labelName.'([ ]?.*?)>/', $str,$match);
        if( isset($match[1]) ){
            if( 'img' == $_labelName ){
                foreach ($match[1] AS $waitReject){
                    preg_match('/.+[src|SRC]=["|\'|“|‘]([\w\W^\s]+)["|\'|“|‘] /i', $waitReject,$srcValue);
                    if( empty($srcValue[1]) ){	
                        $_tamp = explode(' ', $waitReject);
                        foreach ($_tamp AS $attrName){
                            if( 0 === strpos(trim($attrName),'src=') || 0 === strpos(trim($attrName),'SRC=') ){
                                $srcValue[1] = substr($attrName,5,-1);
                                break;
                            }
                        }
                        unset($_tamp,$attrName);
                    }else{					
                        //preg_match('/.+[src|SRC]=["|\'|“|‘]([\w\W^\s]+)["|\'|“|‘] /i', $waitReject,$srcValue);
                        $_tamp = strpos($srcValue[1], '"');
                        if( $_tamp ){
                            $srcValue[1] = substr($srcValue[1], 0, $_tamp);
                        }
                        unset($_tamp);
                    }
                    $str = str_replace($waitReject, ' src="'.$srcValue[1].'" /',$str);
                }
                unset($srcValue);
            }else{
                foreach ($match[1] AS $waitReject){
                    $str = str_replace($waitReject, ' ',$str);
                }
            }
            unset($waitReject);
        }
    }
    unset($match);
    // 去除无数据标签
    foreach ($_retainLabel AS $_labelName){
        $_labelName = substr($_labelName,1,-1);
        if( in_array($_labelName, ['img']) ){
            continue;
        }
        preg_match_all('/(<'.$_labelName.'>(\s|&nbsp;)?<\/'.$_labelName.'>)/i', $str,$match);
        if( isset($match[1]) ){
            foreach ($match[1] AS $waitRemove){
                $str = str_replace($waitRemove, '',$str);
            }
        }
    }
    $str = str_replace(['&nbsp;','&nbsp'], '',$str);
    return preg_replace('/\s(?=\s)/', '', $str);
}