<?php
namespace App\Http\Sdk\Module;
use App\Http\Sdk\SdkModuleInterface;
use App\Http\Sdk\SdkModuleBase;
use Illuminate\Http\Request;
use App\Extend\Sms\Tencent;

class DkVerifyCodeSend extends SdkModuleBase implements SdkModuleInterface
{
    public $sub_must = ['account_type','account'];

    # 内部使用属性
    private static $sandType = ['phone']; // mailbox
    private static $reissueTime  = 60; // 下次发起发送验证码间隔时间，单位秒(s)
    private static $reissueLabel = '~s~秒后重试';// 发送按钮提示

    public function getApiMethodName() : string
    {
        return 'dk.verify.code.send';
    }

    public function businessRun(Request $request)
    {
        if( !in_array($this->bizContent['account_type'],self::$sandType) )
        {
            self::setError('UNKNOW_ERROR','aop.unknow-error','发送类型暂不支持');
            return false;
        }

        if( $this->bizContent['account_type'] === 'mailbox' ){
            // 邮箱验证码预留
            return false;
        }else{
            if(!$this->_sand())return false;
        }

        return [
            'code' => 'SUCCESS',
            'data' => [
                'reissue_label' => self::$reissueLabel,
                'reissue_time' => intval(self::$reissueTime)
            ]
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

    private function _sand() : bool
    {
        $_code = Tencent::createCode($this->bizContent['account']);
        if( Tencent::send($this->bizContent['account'],$_code) ){
            return true;
        }
        self::setError('BUSINESS_FAILED','aop.invalid-fail','验证码发送失败');
        return false;
    }
}
