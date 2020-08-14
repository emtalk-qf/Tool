<?php
namespace App\Sdk\Module;
use Illuminate\Http\Request;
use App\Sdk\SdkModuleInterface;
use App\Sdk\SdkModuleBase;

class Init extends SdkModuleBase implements SdkModuleInterface
{

    public $sub_must = [];
    # 内部调用参数
    private $apiParam = [];

    public function getApiMethodName() : string
    {
        return 'init';
    }

    public function businessRun(Request $request)
    {
        $_data = [];
        return [
            'code' => 'SUCCESS',
            'data' => $_data
        ];
    }

    public function setCommonParam(array $commonParam) : void
    {
        $this->common_param = $commonParam;
        $this->apiParam['common_param'] = $commonParam;
    }

    public function getCommonParam($name=null)
    {
        if(is_null($name))return $this->common_param;
        return data_get($this->common_param,$name);
    }

    public function setBizContent($bizContent) : void
    {
        $this->bizContent = $bizContent['biz_content'];
        $this->apiParam['biz_content'] = $bizContent['biz_content'];
    }

    public function getBizContent($name=null)
    {
        if(is_null($name))return $this->bizContent;
        return data_get($this->bizContent,$name);
    }

}
