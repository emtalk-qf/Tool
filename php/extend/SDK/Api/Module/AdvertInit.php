<?php
namespace App\Sdk\Module;
use Illuminate\Http\Request;
use App\Sdk\SdkModuleInterface;
use App\Sdk\SdkModuleBase;

class AdvertInit extends SdkModuleBase implements SdkModuleInterface
{

    public $sub_must = [];
    # 内部调用参数
    private $apiParam = [];

    public function getApiMethodName() : string
    {
        return 'advert.init';
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

    public function getCommonParam()
    {
        return $this->common_param;
    }

    public function setBizContent($bizContent) : void
    {
        $this->bizContent = $bizContent['biz_content'];
        $this->apiParam['biz_content'] = $bizContent['biz_content'];
    }

    public function getBizContent()
    {
        return $this->bizContent;
    }

}
