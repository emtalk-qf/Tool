<?php
namespace App\Sdk\Enum;
use App\Sdk\Unit\Enum;

class CODE extends Enum
{
    const __default         = [20000,'服务不可用'];
    const SUCCESS           = [10000,'接口调用成功'];
    const UNKNOW_ERROR      = [20000,'服务不可用'];
    const INVALID_TOKEN     = [20001,'授权权限不足'];
    const MISSING_SIGNATURE = [40001,'缺少必选参数'];
    const INVALID_PARAMETER = [40002,'非法的参数'];
    const BUSINESS_FAILED   = [40004,'业务处理失败'];
}
