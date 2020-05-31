<?php
/**
 * 统一请求发送接口
 *
 * @access  public
 * @version 1.0.0
 * @author  Yunuo <ciwdream@gmail.com>
 *
 * @param string $mode      请求模式：GET、POST
 * @param string $url       请求地址
 * @param array  $data      请求数据,若请求模式为GET则默认空数组
 * @param array  $config    其他配置参数
 * @param string $dataType  返回数据格式
 *
 * @return mixed
 */
function requestMode($mode, $url, $data = [], array $config = [], $dataType = null)
{
    if (!in_array($mode, ['GET', 'POST'])) {
        return null;
    }
    if( !empty($_curlDB) || isset($_curlDB) ){
        unset($_curlDB);
    }
    try {
        $_curlDB = curl_init();
        curl_setopt($_curlDB,CURLOPT_URL, $url);
        # 创建CURL其他配置
        if( !empty($config) ){
            static $_curlConstList;
            if( !$_curlConstList ){
                $_curlConstList = get_defined_constants(true);
                $_curlConstList = $_curlConstList['curl'];
            }
            foreach ($config AS $_cName => $_cValue)
            {
                $_tampName = 'CURLOPT_'.strtoupper($_cName);
                if( !isset($_curlConstList[$_tampName]) ){
                    continue;
                }
                curl_setopt($_curlDB, $_curlConstList[$_tampName], $_cValue);
                unset($_tampName);
            }
            unset($_cName,$_cValue);
        }
        if ( strpos($url,'https://') === 0 ) {
            curl_setopt($_curlDB, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查,跳过检查
            curl_setopt($_curlDB, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在,跳过检查
////                curl_setopt($_curlDB,CURLOPT_CAINFO,dirname(__FILE__).'/cacert.pem');
        }
        if ('POST' == $mode) {
            curl_setopt($_curlDB, CURLOPT_POST, true);
            curl_setopt($_curlDB, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($_curlDB, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($_curlDB, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($_curlDB, CURLOPT_MAXREDIRS, 7);
        curl_setopt($_curlDB, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($_curlDB, CURLOPT_TIMEOUT, 10);  // 请求最大时长
        curl_setopt($_curlDB, CURLOPT_HEADER, false);
        curl_setopt($_curlDB, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($_curlDB, CURLOPT_NOBODY, false);
        curl_setopt($_curlDB, CURLINFO_HEADER_OUT, true); //启用时追踪句柄的请求字符串。
        $return = curl_exec($_curlDB);
        if( $return === false ){
            Log::error('[requestMode]Curl请求错误代码:'.curl_errno($_curlDB).' $mode:'.$mode.' $url:'.$url.' $data:'.(is_array($data)?json_encode($data):$data));
            return 'error';
        }
        curl_close($_curlDB);

        # 判断返回数据类型
        if( 'GET' == $mode ){
            $return = ( $return === false ) ? file_get_contents($url) : $return;
        }
    }catch (\Exception $exception){
        return null;
    }
    $_requestDataFormat = function($data, $dataType=null){
        if( null != $dataType ){
            switch ($dataType){
                case 'json':
                    if( self::isJson($data) ){
                        $data = json_decode($data,true);
                    }else{
                        Log::error('[requestMode]IS_JSON错误:'.$data);
                        $data = null;
                    }
                    break;
                case 'xml':
                    if( self::isXml($data) ){
                        $data = json_decode(json_encode(simplexml_load_string($data)),true);
                    }else{
                        $data = null;
                    }
                    break;
                case 'text':
                    $data = $data;
                    break;
                default :
                    $data = null;
            }
        }
        return $data;
    };
    return $_requestDataFormat($return, $dataType);
}