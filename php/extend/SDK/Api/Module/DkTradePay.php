<?php
namespace App\Http\Sdk\Module;
use App\Helper\Tools\Fun;
use App\Http\Sdk\SdkModuleBase;
use App\Http\Sdk\SdkModuleInterface;
use App\Model\SystemPayChannelModel as mSysPayChannel;
use App\Model\GameModel as mGame;
use App\Model\GameDistrictModel as mGameDistrict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class DkTradePay extends SdkModuleBase implements SdkModuleInterface
{
    public $isAuthUser = true;
    public $sub_must = [];

    # 内部变量
    private $__payChannel = [];

    public function _initialize()
    {
        $this->sub_must = $this->_formRule();
    }

    public function getApiMethodName() : string
    {
        // 交易订单统一下单接口
        return 'dk.trade.pay';
    }

    public function businessRun(Request $request)
    {
        $this->_HandleInit();
        if(self::isError())return false;
        return $this->_pay($request);
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
//        $bizContent['biz_content']['channel'] = 'alipay';
//        $bizContent['biz_content']['out_trade_no'] = 'test_'.time();
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
        // 验证支付渠道
        $this->_payChannel = mSysPayChannel::getAllList(['platform_move'=>1],'',['name_as as channel','name_ass','id','config']);
        $_payChannel = Fun::array_key_change($this->_payChannel,'channel');
        if( !isset($_payChannel[$this->bizContent['channel']]) ){
            self::loggers('[SDK -> 支付]channel渠道超出：'.$this->bizContent['channel'],'notice');
            self::setError('UNKNOW_ERROR','isp.unknow-error','支付类型暂不可用');
            return;
        }
        $this->bizContent['channel_config'] = $_payChannel[$this->bizContent['channel']];
        if( !empty($this->bizContent['channel_config']['config']) ){
            $this->bizContent['channel_config']['config'] = json_decode($this->bizContent['channel_config']['config'],true);
        }
        return;
    }

    /**
     * 启动支付流程
     */
    private function _pay(Request $request)
    {
        $param = [
            'order_no' => Fun::orderNumberBuild(1),
            'gameNumber' => isset($this->bizContent['out_trade_no']) ? $this->bizContent['out_trade_no'] : '',
            'uid' => $this->_authUser->user()->id,
            'channel' => intval($this->bizContent['channel_config']['id']),
            'money' => $this->bizContent['total_fee'] / 100,
            'payName' => '测试充值1',
            'location' => route('h5_home',['sdk'=>'lidao'])
        ];
        // 游戏充值
        $_tampGame = mGame::getFirstInfo($this->bizContent['gkey'],'alias',['id','name','isdebug']);
        if( !$_tampGame ){
            self::loggers('[SDK -> 支付]Game不存在：'.$this->bizContent['gkey'],'notice');
            self::setError('UNKNOW_ERROR','isp.unknow-error','游戏不存在或暂不可用');
            return false;
        }
        $_isDebug = (0 == $_tampGame['isdebug']) ? true : false;
        $_tampGame['districtid'] = mGameDistrict::getGameFirstInfo($_tampGame['id'],$this->bizContent['skey'],'name',['id','front_name']);

        $param['type'] = 2;
        $param['gid']  = $_tampGame['id'];
        $param['districtid'] = $_tampGame['districtid']['id'];
        $param['payName'] = '【'.$_tampGame['name'].'】-【'.$_tampGame['districtid']['front_name'].'】订单付款';
        unset($_tampGame);

        // 触发支付渠道
        $request->merge($param);
        $_class = '\App\\Paychannel\\'.ucwords($this->bizContent['channel']);
        $_app = \App::make($_class);
        $_app::$userInfo = $this->_authUser->user();
        $_app::$isDebug = $_isDebug;
        $_rs = $_app->inside_pay($request,$this->bizContent['channel_config']['name_ass'],$this->bizContent['trade_type']);
        if( $_rs['errMsg'] === '0000' || $_rs['errMsg'] === '0001' ){
            if( 'platform' != $this->bizContent['channel'] ){
                if( empty($_rs['data']['image']) ){
                    self::loggers('[SDK -> 支付]支付渠道失败：'.var_export($_rs,true),'notice');
                    return [
                        'code' => 'BUSINESS_FAILED',
                        'sub_code' => 'aop.handle-fail',
                        'sub_msg' => '支付失败',
                        'data' => []
                    ];
                }
                $_cacheKey  = md5($_rs['data']['image']);
                $_cacheName = 'SDK_ViewCode_'.$_cacheKey;
                if( !Redis::exists($_cacheName) ){
                    Redis::setex($_cacheName,3600,$_rs['data']['image']); // 缓存一小时
                }
                $_rs['data']['image'] = route('sdk_gateway_view',['type'=>'pay_view','code'=>$_cacheKey]);
                $_rs['data']['out_trade_no'] = $param['order_no'];
            }
            return [
                'code' => 'SUCCESS',
                'data' => $_rs['data']
            ];
        }else{
            return [
                'code' => 'BUSINESS_FAILED',
                'sub_code' => 'aop.handle-fail',
                'sub_msg' => 'platform' == $this->bizContent['channel'] ? '余额不足' : '支付失败',
                'data' => []
            ];
        }
    }

    # 业务参数验证规则
    private function _formRule()
    {
        return [
            // 支付渠道标识
            'channel',
            // 验证支付类型
            'trade_type' => [
                'rule'=>'in:app',// ,'web','jsapi'
                'tips'=>[
                    'in' => '类型暂不可用'
                ]
            ],
            // 游戏标识
            'gkey',
            // 游戏区服标识
            'skey',
            // 订单号
            'out_trade_no',
            // 充值金额，单位分
            'total_fee',
            // 客户端IP
            'spbill_create_ip'
        ];
    }

}
