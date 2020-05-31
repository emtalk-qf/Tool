<?php
namespace App\Http\Sdk\Module;
use App\Helper\Tools\Fun;
use App\Http\Sdk\SdkModuleInterface;
use App\Http\Sdk\SdkModuleBase;
use App\Model\SystemUserModel as mSystemUser;
use GuzzleHttp\Client as HttpCurl;
use Illuminate\Http\Request;
use App\Model\SystemUserModel AS mSysUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DkThirdUserLogin extends SdkModuleBase implements SdkModuleInterface
{
    public $sub_must = ['channel','openid'];
    # 内部调用参数
    private $apiParam = [];
    private static $channelType = ['weixin','qq','weibo'];

    public function getApiMethodName() : string
    {
        return 'dk.third.user.login';
    }

    public function businessRun(Request $request)
    {
        $this->_HandleInit();
        if(self::isError())return false;

        // 判断是否存在平台账号
        if( mSysUser::isExist($this->bizContent['openid'],$this->bizContent['channel']) ){
            $rsData = mSysUser::getFirstInfo($this->bizContent['openid'],$this->bizContent['channel'],['id','nickname','portrait','api_token','password']);
            // 更新token,多次登录token变更
            $rsData['api_token'] = hash('sha256', Str::random(60).time());
            mSysUser::whereUpdate(['id'=>$rsData['id']],['api_token'=>$rsData['api_token']]);
        }else{
            // 进行新账号创建
            $rsData = [
                'account' => Fun::nicknameBuild('w_'),
                'nickname' => '',
                $this->bizContent['channel'] => $this->bizContent['openid'],
                'password' => '',
                'last_login_ip' => $request->getClientIp()
            ];
            $rsData['nickname'] = isset($this->bizContent['nickname']) ? $this->bizContent['nickname'] : $rsData['account'];
            if( isset($this->bizContent['avatar']) ){
                $rsData['portrait'] = $this->bizContent['avatar'];
            }
            $rsData['password'] = Hash::make($rsData['account'].$rsData['nickname']);
            $rsData['api_token'] = hash('sha256', Str::random(60).time());
            $rsData['last_login_time'] = $rsData[mSystemUser::CREATED_AT] = $rsData[mSystemUser::UPDATED_AT] = time();
            if( !mSystemUser::insert($rsData) ){ // 创建失败
                self::setError('BUSINESS_FAILED','isp.unknow-error','账号创建失败');
                return;
            }
        }

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
        if( !in_array($this->bizContent['channel'],self::$channelType) ){
            self::setError('UNKNOW_ERROR','isp.unknow-error','注册类型暂不可用');
            return;
        }
        return;
    }

}
