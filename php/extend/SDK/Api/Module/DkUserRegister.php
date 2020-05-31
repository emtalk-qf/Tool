<?php
namespace App\Http\Sdk\Module;
use App\Helper\Tools\Fun;
use App\Http\Sdk\SdkModuleInterface;
use App\Http\Sdk\SdkModuleBase;
use Illuminate\Http\Request;
use App\Model\SystemUserModel AS mSysUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DkUserRegister extends SdkModuleBase implements SdkModuleInterface
{
    public $sub_must = ['account_type','account','password'];
    # 内部调用参数
    private $apiParam = [];
    private static $AccountType = ['account','phone'];// mailbox

    public function getApiMethodName() : string
    {
        return 'dk.user.register';
    }

    public function businessRun(Request $request)
    {
        $this->_HandleInit();
        if(self::isError())return false;

//        $this->bizContent['account_type'] = 'account';
//        $this->bizContent['account'] = 'yunuo9110';
//        $this->bizContent['captcha'] = '123456';

        $_addData = [];
        $_addData['account'] = $this->bizContent['account'];
        $_addData['password'] = $this->bizContent['password'];

        // 表单验证
        if( !$this->_validator() )return false;

        // 预备入库
        $this->_dbCreate($_addData,$rsData);
        if( empty($rsData) )return false;

        if( self::isError() ){ // 此时若错误信息不为空，则可能是重复注册
            $_errerInfo = self::getError();
            return [
                'code' => 'SUCCESS',
                'sub_code' => 'aop.invalid-repeat-data',
                'sub_msg' => $_errerInfo['sub_msg'],
                'data' => [
                    'userId'   => intval(empty($rsData['id'])?0:$rsData['id']),
                    'userName' => strval(empty($rsData['nickname'])?'Null':$rsData['nickname']),
                    'userAvatar' => asset(empty($rsData['portrait'])?'/resource/image/default_288x370.png':$rsData['portrait']),
                    self::getConfigs('tokenName') => $rsData['api_token']
                ]
            ];
        }

        return [
            'code' => 'SUCCESS',
            'data' => [
                'userId'   => intval(empty($rsData['id'])?0:$rsData['id']),
                'userName' => strval(empty($rsData['nickname'])?'Null':$rsData['nickname']),
                'userAvatar' => asset(empty($rsData['portrait'])?'/resource/image/default_288x370.png':$rsData['portrait']),
                self::getConfigs('tokenName') => $rsData['api_token']
            ]
        ];
    }

    public function setCommonParam(array $commonParam) : void
    {
        $this->common_param = $commonParam;
        $this->apiParam['common_param'] = $commonParam;
    }

    public function getCommonParam() : array
    {
        return $this->common_param;
    }

    public function setBizContent($bizContent) : void
    {
        $this->bizContent = $bizContent['biz_content'];
        $this->apiParam['biz_content'] = $bizContent['biz_content'];
    }

    public function getBizContent() : array
    {
        return $this->bizContent;
    }

    /**
     * 初始化
     */
    private function _HandleInit() : void
    {
        if( !in_array($this->bizContent['account_type'],self::$AccountType) ){
            self::setError('UNKNOW_ERROR','isp.unknow-error','注册类型暂不可用');
            return;
        }
        return;
    }

    /**
     * 表单数据验证
     */
    private function _validator() : bool
    {
        $_data = $this->bizContent;
        unset($_data['account_type']);

        $_rules = [ // 验证顺序
            'account' => [],
            'password' => ['required','between:6,20'],
        ];

        $_messages = [
            'account.required' => '请填写注册账号',
            'account.allow_letter_number' => '账号必须为英文+数字的格式',
            'account.email' => '请填写正确的邮箱账号',
            'account.numeric' => '请填写正确的手机号码',
            'account.between' => '账号长度需要在:min~:max位之间',
            'account.digits' => '账号最长只能为11位手机号码',
            'password.required' => '请填写用于账号登录的密码',
            'password.between' => '密码长度需要在:min~:max位之间',
            'captcha.digits' => '请检查验证码是否正确'
        ];

        // 根据账号不同切换验证规则
        switch ($this->bizContent['account_type']){
            case self::$AccountType[1]: // 手机
                $_rules['account'] = ['required','numeric','digits:11'];
                // 验证手机验证码
                $_rules['captcha'] = ['required','numeric','digits_between:4,6'];
                $_messages['captcha.digits_between'] = '验证码长度需要在:min~:max位之间';
                break;
//            case self::$AccountType[2]: // 邮箱
//                $_rules['account'] = ['required','email:rfc'];
//                // 验证邮箱验证码
//                $_rules['captcha'] = ['required','numeric','digits:6'];
//                break;
            default:
                // 必填,字母+数字，长度最大11位
                $_rules['account'] = ['required','allow_letter_number','between:6,20'];
        }

        $_validator = validator($_data,$_rules, $_messages);
        if( $_validator->fails() ){
            self::setError('BUSINESS_FAILED','aop.invalid-param-error',$_validator->errors()->first());
            return false;
        }else{
            // 预留验证码验证扩展
            $_verifCode = \App\Extend\Sms\Tencent::verifCode($_data['account'],$_data['captcha']);
            if( false === $_verifCode || \App\Helper\CommonFun::Enum('ENUM_HTTP_CLIENT_HEAD.HTTP_ERROR_ALLOW_NON_EXISTENT') === $_verifCode ){
                self::loggers('[SDK -> 支付]验证码错误：'.var_export($_verifCode,true),'notice');
                self::setError('BUSINESS_FAILED','aop.invalid-param-error','验证码错误');
                return false;
            }
//            \App\Extend\Sms\Tencent::deleteCode($_data['account']);
            return true;
        }
    }

    /**
     * 数据入库
     *
     * @param array $data 待入库数据
     * @param array $callbackVar 引用变量，创建数据成功，回传插入前数据
     *
     * @return bool
     */
    private function _dbCreate(array $data,&$callbackVar) : bool
    {
        // 整合创建数据
        if( mSysUser::isExist($data['account'],$this->bizContent['account_type']) ){
            $callbackVar = mSysUser::getFirstInfo($this->bizContent['account'],$this->bizContent['account_type'],['id','nickname','portrait','api_token','password','last_login_ip','last_login_time']);
            if( empty($callbackVar['api_token']) ){
                $callbackVar['api_token'] = hash('sha256', Str::random(60).time());
                mSysUser::whereUpdate(['id'=>$callbackVar['id']],[
                    'api_token' => $callbackVar['api_token']
                ]);
            }
            self::setError('BUSINESS_FAILED','aop.invalid-repeat-data','账号已存在，请勿重复注册');
            return false;
        }

        if( in_array($this->bizContent['account_type'],[self::$AccountType[1]]) ){
            $data[$this->bizContent['account_type']] = $data['account'];
            $data['account'] = $data['nickname'] = Fun::accountBuild();
        }
        $data['api_token'] = hash('sha256', Str::random(60).time());
        // 对明文密码进行处理
        $data['password'] = Hash::make($data['password']);
        $data['last_login_ip'] = \App\Helper\Tools\Fun::getUserHostAddress();
        $data['last_login_time'] = time();
        if( !$data['id'] = mSysUser::add($data) )
        {
            self::setError('BUSINESS_FAILED','aop.invalid-auth-register','注册失败');
            $callbackVar = [];
            return false;
        }
        $callbackVar = $data;
        return true;
    }
}
