<?php
namespace App\Http\Sdk\Module;
use App\Extend\Sms\Tencent;
use App\Helper\Tools\Fun;
use App\Http\Sdk\SdkModuleInterface;
use App\Http\Sdk\SdkModuleBase;
use App\Model\SystemUserModel AS mSysUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DkUserLogin extends SdkModuleBase implements SdkModuleInterface
{
    public $sub_must = ['account','password'];
    # 内部调用参数
    private $apiParam = [];
    private static $AccountType = ['account','phone'];// mailbox

    # 密码错误限制
    private static $PasswordFailMaxNum = 3;
    private static $PasswordFailTime   = 600; // 单位秒(s)

    public function getApiMethodName() : string
    {
        return 'dk.user.login';
    }

    public function businessRun(Request $request)
    {
        // 表单提交验证
        if( !$this->_validator() )return false;

        // 账号数据验证
        $this->_dbVerify($rsData);
        if( empty($rsData) )return false;

        // 更新token,多次登录token变更
        $rsData['api_token'] = hash('sha256', Str::random(60).time());
        mSysUser::whereUpdate(['id'=>$rsData['id']],[
            'api_token' => $rsData['api_token']
        ]);
        return [
            'code' => 'SUCCESS',
            'data' => [
                'userId'   => intval(empty($rsData['id'])?0:$rsData['id']),
                'userName' => strval(empty($rsData['nickname'])?'Null':$rsData['nickname']),
                'userAvatar' => asset(empty($rsData['portrait'])?'/resource/image/default_288x370.png':$rsData['portrait']),
                self::getConfigs('tokenName') => strval($rsData['api_token']),
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

    /**
     * 表单数据验证
     */
    private function _validator() : bool
    {
        $_data = $this->bizContent;

        // 判断账号类型
        $this->bizContent['account_type'] = Fun::accountType($_data['account']);

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
                $_rules['password'] = ['required','numeric','digits_between:4,6'];
                $_messages['password.digits_between'] = '验证码长度需要在:min~:max位之间';
                break;
//            case self::$AccountType[2]: // 邮箱
//                $_rules['account'] = ['required','email:rfc'];
//                // 验证邮箱验证码
//                $_rules['password'] = ['required','numeric','digits:6'];
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
            return true;
        }
    }

    /**
     * 数据验证
     *
     * @param array $data 待入库数据
     * @param array $callbackVar 引用变量，创建数据成功，回传插入前数据
     *
     * @return bool
     */
    private function _dbVerify(&$callbackVar) : bool
    {
        $_cacheName = 'SDK_'.$this->bizContent['account'].'_PasswordFail';
        if( Redis::exists($_cacheName) ){
            $_cacheData = Redis::get($_cacheName);
            $_cacheData = json_decode($_cacheData,true);
            if( $_cacheData['num'] > self::$PasswordFailMaxNum ){
                self::setError('INVALID_TOKEN','aop.invalid-auth-limit','密码错误次数超过3次，请10分钟后重试');
                return false;
            }
            unset($_cacheData);
        }
        $_dbData = mSysUser::getFirstInfo($this->bizContent['account'],$this->bizContent['account_type'],['id','nickname','portrait','api_token','password']);
        if( isset($_dbData['id']) ){
            if( $this->bizContent['account_type'] === self::$AccountType[0] ){ // 普通账号验证
                // 验证密码是否正确
                if( Hash::check($this->bizContent['password'],$_dbData['password']) ){
                    if( Redis::exists($_cacheName) ){
                        Redis::del($_cacheName);
                    }
                    $callbackVar = $_dbData;
                    return true;
                }else{
                    $_failNum = 1;
                    if( Redis::exists($_cacheName) ){
                        $_cacheData = Redis::get($_cacheName);
                        $_cacheData = json_decode($_cacheData,true);
                        $_failNum = intval($_cacheData['num']) + 1;
                        unset($_cacheData);
                    }
                    Redis::setex($_cacheName,self::$PasswordFailTime,json_encode([
                        'account' => $this->bizContent['account'],
                        'num' => $_failNum
                    ]));
                    self::setError('BUSINESS_FAILED','aop.invalid-param-error','密码错误，请重试');
                    return false;
                }
            }else{
                if( $this->codeVerif() ){
                    $callbackVar = $_dbData;
                    return true;
                }
                return false;
            }
        }else{
            self::setError('BUSINESS_FAILED','aop.invalid-auth-login','账号不存在，请查证后重试');
            return false;
        }
    }

    private function codeVerif() : bool
    {
        $_cacheName = 'SDK_'.$this->bizContent['account'].'_PasswordFail';
        if( $this->bizContent['account_type'] === 'mailbox' ){
            // 邮箱验证码预留
            return false;
        }else{
            if( Tencent::verifCode($this->bizContent['account'],$this->bizContent['password']) === true ){
                Tencent::deleteCode($this->bizContent['account']);
                if( Redis::exists($_cacheName) ){
                    Redis::del($_cacheName);
                }
                return true;
            }
            $_failNum = 1;
            if( Redis::exists($_cacheName) ){
                $_cacheData = Redis::get($_cacheName);
                $_cacheData = json_decode($_cacheData,true);
                $_failNum = intval($_cacheData['num']) + 1;
                unset($_cacheData);
            }
            Redis::setex($_cacheName,self::$PasswordFailTime,json_encode([
                'account' => $this->bizContent['account'],
                'num' => $_failNum
            ]));
            self::setError('BUSINESS_FAILED','aop.invalid-param-error','验证码错误，请重试');
            return false;
        }
    }
}
