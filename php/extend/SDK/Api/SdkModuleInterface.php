<?php
namespace App\Sdk;
use Illuminate\Http\Request;

/**
 * SDK相关功能模块接口类
 *
 * @version  1.0.0
 * @author   Yunuo <ciwdream@gmail.com>
 */
interface SdkModuleInterface
{
    /**
     * 模块名称
     */
    public function getApiMethodName() : string;
    /**
     * 模块业务触发方法
     *
     * @param Illuminate\Http\Request $request
     *
     * @return 格式规范：
     * return [
     *     'code' => 'SUCCESS', // 参考app/Enum/ENUM_SDK_CODE.php
     *     'sub_code' => '',    // 选填，业务返回码，参见具体的API接口文档，例：isv.invalid-signature
     *     'sub_msg'  => '',    // 选填，业务返回码描述
     *     'data' => []         // 选填，需要反馈给客户端的数据
     * ];
     */
    public function businessRun(Request $request);
    /**
     * 设置公共参数
     *
     * @param array $commonParam
     *
     * @return void
     */
    public function setCommonParam(array $commonParam) : void;
    /**
     * 获取公共参数
     *
     * @param string $name // (可选)key名称，支持“点”
     * @return mixed
     */
    public function getCommonParam($name=null);
    /**
     * 设置业务参数
     *
     * @param mixed $bizContent
     * @return bool
     */
    public function setBizContent($bizContent) : bool ;
    /**
     * 获取业务参数
     *
     * @param string $name // (可选)key名称，支持“点”
     * @return mixed
     */
    public function getBizContent($name=null);

    /**
     * 错误统一回调方法
     *
     * use App\Http\Sdk\Traits\Handler;
     *
     */
    public function _handler() : array;
}
