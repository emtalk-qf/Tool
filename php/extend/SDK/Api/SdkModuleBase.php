<?php
namespace App\Http\Sdk;

use App\Http\Sdk\Traits\Config as SdkConfig;
use App\Http\Sdk\Traits\Handler as SdkHandler;
use App\Http\Sdk\Traits\Log as SdkLog;
use App\Http\Sdk\Traits\Auth as SdkAuth;

class SdkModuleBase
{
    use SdkHandler,
        SdkConfig,
        SdkLog,
        SdkAuth;

    public function __construct()
    {
        if (method_exists($this, '_initialize')) {
            $this->_initialize();
        }
    }

    /**
     * 是否需要授权用户信息，由网关控制器进行验证
     */
    public $isAuthUser = false; // false不需要 true需要
    /**
     * 当前调试模式：true调试模式 false正式模式，子模块仅限于用于获取，不参与修改
     */
    public $thisIsDebug = false;

    /**
     * 业务参数必填字段名称，由网关控制器进行验证
     */
    public $sub_must = [];
}
