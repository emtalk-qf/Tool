<?php
/**
 * 计算几分钟前、几小时前、几天前
 * 
 * @access  public
 * @version 0.0.1
 * @author  Yunuo <ciwdream@gmail.com>
 * 
 * @param int $addtime 发布时间戳
 *  
 * @return string
 */
function time_tran($the_time) {
    $now_time  = time();
    $show_time = intval($the_time);
    $dur = $now_time - $show_time;
    if ($dur < 0) {
        return '刚刚';
    } else {
        if ($dur < 60) {
            return $dur . '秒前';
        } else {
            if ($dur < 3600) {
                return floor($dur / 60) . '分钟前';
            } else {
                if ($dur < 86400) {
                    return floor($dur / 3600) . '小时前';
                } else {
                    if ($dur < 259200) {//3天内
                        return floor($dur / 86400) . '天前';
                    } else {
                        return date('m/d H:i', $the_time);
                    }
                }
            }
        }
    }
}