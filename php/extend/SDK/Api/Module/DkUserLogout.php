<?php
namespace App\Http\Sdk\Module;
use App\Http\Sdk\SdkModuleInterface;
use App\Http\Sdk\SdkModuleBase;
use App\Model\SystemUserModel as mSysUser;
use Illuminate\Http\Request;

class DkUserLogout extends SdkModuleBase implements SdkModuleInterface
{

    public $sub_must = ['access_token'];

    public function getApiMethodName() : string
    {
        return 'dk.user.logout';
    }

    public function businessRun(Request $request)
    {
        if( empty($this->common_param[self::getConfigs('tokenName')]) ){
            self::setError('INVALID_TOKEN','aop.invalid-data','授权Token数据不可为空');
            return false;
        }

        $_dbData = mSysUser::getFirstInfo($this->common_param[self::getConfigs('tokenName')],'api_token',['id']);
        if( !array_key_exists('id',$_dbData) ){
            self::setError('BUSINESS_FAILED','aop.invalid-param-error','授权Token数据不可为空');
            return false;
        }

        if( !mSysUser::whereUpdate(['api_token'=>$this->common_param[self::getConfigs('tokenName')]],[
            'api_token' => null
        ]) ){
            self::setError('BUSINESS_FAILED','aop.invalid-auth-error','无效退出或退出失败');
            return false;
        }

        return [
            'code' => 'SUCCESS',
            'data' => []
        ];
    }

    public function setCommonParam(array $commonParam) : void
    {
        foreach ($commonParam AS $name=>$value)
        {
            try {
                $value = trim($value);
            }catch (\Exception $e){
                $value = null;
            }
            if( empty($value) ){
                unset($commonParam[$name]);
            }
        }
        unset($name,$value);
        $this->common_param = $commonParam;
    }

    public function getCommonParam()
    {
        return $this->common_param;
    }

    public function setBizContent($bizContent) : void
    {
    }

    public function getBizContent()
    {
    }
}
