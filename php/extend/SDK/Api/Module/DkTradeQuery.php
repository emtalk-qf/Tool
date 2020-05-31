<?php
namespace App\Http\Sdk\Module;
use App\Helper\Tools\Fun;
use App\Http\Sdk\SdkModuleBase;
use App\Http\Sdk\SdkModuleInterface;
use Illuminate\Http\Request;
use App\Model\LogRechargeModel AS mLogRecharge;
use Illuminate\Support\Arr;

class DkTradeQuery extends SdkModuleBase implements SdkModuleInterface
{

    public $isAuthUser = true;
    public $sub_must = ['out_trade_no'];

    public function getApiMethodName() : string
    {
        // 交易查询接口
        return 'dk.trade.query';
    }

    public function businessRun(Request $request)
    {
        $_data = mLogRecharge::getWhereFirstInfo([
            'uid' => $this->_authUser->user()->id,
            'dk_number' => $this->bizContent['out_trade_no']
        ],['state']);
        if( empty($_data['state']) ){
            self::loggers('[SDK -> 交易查询]订单不存在$out_trade_no：'.$this->bizContent['out_trade_no'],'notice');
            self::setError('BUSINESS_FAILED','aop.unknow-trade-number','订单号不存在！');
            return false;
        }
        $_payState = \App\Helper\CommonFun::Enum('ENUM_PAY_STATE.');
        $_payState = \App\Helper\Tools\Fun::array_key_change(array_values($_payState),'value');
        if( !isset($_payState[$_data['state']]) ){
            self::loggers('[SDK -> 交易查询]订单状态超出预设，$out_trade_no：'.$this->bizContent['out_trade_no'].' $state:'.$_data['state'],'error');
            self::setError('BUSINESS_FAILED','aop.unknow-trade-number','订单号查询异常，若多次尝试后依旧，请联系管理员！');
            return false;
        }
        return [
            'code' => 'SUCCESS',
            'data' => [
                'out_trade_no' => $this->bizContent['out_trade_no'],
                'state' => $_payState[$_data['state']]['value'],
                'state_label' => $_payState[$_data['state']]['name']
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
}
