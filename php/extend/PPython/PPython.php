<?php
namespace App\Extended;
// +----------------------------------------------------------------------
// | 客房管家 [ Housekeeping Manager ]
// +----------------------------------------------------------------------
// | 第三方库 - PPython - PHP与Python交互处理类
// +----------------------------------------------------------------------
// | Copyright (c) 2017 All rights reserved.
// +----------------------------------------------------------------------
// | Author: Yunuo <ciwdream@gmail.com>
// +----------------------------------------------------------------------
class PPython {
    
    private static $_CONFIG = [];
    private static $_ISINIT = false;

    public static function init(array $config = [])
    {
        self::$_CONFIG['LAJP_IP']          = isset($config['LAJP_IP'])?$config['LAJP_IP']:'127.0.0.1'; // Python端IP
        self::$_CONFIG['LAJP_PORT']        = isset($config['LAJP_PORT'])?$config['LAJP_PORT']:21230; // Python端侦听端口
        self::$_CONFIG['PARAM_TYPE_ERROR'] = isset($config['PARAM_TYPE_ERROR'])?$config['PARAM_TYPE_ERROR']:101; // 参数类型错误
        self::$_CONFIG['SOCKET_ERROR']     = isset($config['SOCKET_ERROR'])?$config['SOCKET_ERROR']:102; // SOCKET错误
        self::$_CONFIG['LAJP_EXCEPTION']   = isset($config['LAJP_EXCEPTION'])?$config['LAJP_EXCEPTION']:104; // Python端反馈异常
        self::$_ISINIT = true;
    }
    
    /**
     * 执行Python文件
     * 
     * @param string $py_name Python模块函数名称,例如：crontab::set_crontab
     * @param mixed  [$param] Python模块函数需要的参数 
     * 
     * @return mixed
     */
    public static function call()
    {
        if( !self::$_ISINIT )self::init();
        //参数数量
        $args_len = func_num_args();
        //参数数组
        $arg_array = func_get_args();
        //参数数量不能小于1
        if ($args_len < 1)
        {
            throw new \Exception("[PPython Error] lapp_call function's arguments length < 1", self::$_CONFIG['PARAM_TYPE_ERROR']);
        }
        //第一个参数是Python模块函数名称，必须是string类型
        if (!is_string($arg_array[0]))
        {
            throw new \Exception("[PPython Error] lapp_call function's first argument must be string \"module_name::function_name\".", self::$_CONFIG['PARAM_TYPE_ERROR']);
        }
        
        if (($socket = socket_create(AF_INET, SOCK_STREAM, 0)) === false)  // 创建一个套接字（通讯节点）
        {
            throw new \Exception("[PPython Error] socket create error.", self::$_CONFIG['SOCKET_ERROR']);
        }
        
        if (socket_connect($socket, self::$_CONFIG['LAJP_IP'], self::$_CONFIG['LAJP_PORT']) === false)  // 开启一个套接字连接
        {
            throw new \Exception("[PPython Error] socket connect error.", self::$_CONFIG['SOCKET_ERROR']);
        }

        //消息体序列化
        $request = json_encode($arg_array);
        $req_len = strlen($request);

        $request = $req_len.",".$request;

        echo "{$request}<br>";

        $send_len = 0;
        do
        {
            //发送
            if (($sends = socket_write($socket, $request, strlen($request))) === false)
            {
                throw new \Exception("[PPython Error] socket write error.", self::$_CONFIG['SOCKET_ERROR']);
            }

            $send_len += $sends;
            $request = substr($request, $sends);

        }while ($send_len < $req_len);

        //接收
        $response = "";
        while(true)
        {
            $recv = "";
            if (($recv = socket_read($socket, 1400)) === false)
            {
                throw new \Exception("[PPython Error] socket read error.", self::$_CONFIG['SOCKET_ERROR']);
            }
            if ($recv == "")
            {
                break;
            }

            $response .= $recv;

            echo "{$response}<br>";

        }

        //关闭
        socket_close($socket);

        $rsp_stat = substr($response, 0, 1);    //返回类型 "S":成功 "F":异常
        $rsp_msg = substr($response, 1);        //返回信息

        echo "返回类型:{$rsp_stat},返回信息:{$rsp_msg}<br>";

        if ($rsp_stat == "F")
        {
            //异常信息不用反序列化
            throw new \Exception("[PPython Error] Receive Python exception: ".$rsp_msg, self::$_CONFIG['LAJP_EXCEPTION']);
        }
        else
        {
            if ($rsp_msg != "N") //返回非void
            {
                //反序列化
                return unserialize($rsp_msg);
            }
        }
    }
    
}

PPython::call('test::doits',__DIR__,__DIR__);