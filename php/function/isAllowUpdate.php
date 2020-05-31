<?php
/**
 * 版本号更新判断
 * 
 * @version 0.0.1
 * @author  Yunuo <ciwdream@gmail.com>
 * 
 * @param int $version      当前版本号
 * @param int $newversion   新版版本号
 *  
 * @return bool
 */
function isAllowUpdate($version,$newversion){
    $_Allow = false;
    # 拆分版本为数组
    # 例如: 1.4.1 转化为 array(1,4,1)
    $_version    = explode('.', $version);
    $_newversion = explode('.', $newversion);
    $_forCount   = count($newversion);
    # 循环对同键值位上的值进行大小判断
    for($i=0;$i<=$_forCount;$i++){
        # 将数值转化为数组
        $_tampVer    = str_split($_version[$i]);
        $_tampNewVer = str_split($_newversion[$i]);
        # 以最新版本数组数据为中心进行大小判断
        foreach ($_tampNewVer as $_forI=>$_forVer) {
            if( $_tampVer[$_forI] < $_forVer ){
                $_Allow = true;
                break;
            }elseif( $_tampVer[$_forI] > $_forVer ){
                $_Allow = false;
                break;
            }
        }
    }
    return $_Allow;
}

# 可能出BUG情况记录
/*
    var_dump(isAllowUpdate('1.41','1.401'));
    var_dump(isAllowUpdate('1.401','1.41'));
    var_dump(isAllowUpdate('1.41','1.42'));
    var_dump(isAllowUpdate('1.4','1.42'));
    var_dump(isAllowUpdate('1.0','10.0'));
    var_dump(isAllowUpdate('1.01','10.01'));
    var_dump(isAllowUpdate('10.1','1.01'));
    var_dump(isAllowUpdate('10.1','10.11'));
    var_dump(isAllowUpdate('1.01','1.02'));
    var_dump(isAllowUpdate('1.33','1.34'));
*/