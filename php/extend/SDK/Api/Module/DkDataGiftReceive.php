<?php
namespace App\Http\Sdk\Module;
use App\Http\Sdk\SdkModuleBase;
use App\Http\Sdk\SdkModuleInterface;
use Illuminate\Http\Request;
use App\Model\GameGiftCodeModel AS mGameGiftCode;

class DkDataGiftReceive extends SdkModuleBase implements SdkModuleInterface
{

    public $isAuthUser = true;
    public $sub_must = ['code'];

    public function getApiMethodName() : string
    {
        return 'dk.data.gift.receive';
    }

    public function businessRun(Request $request)
    {
        $_isReceive = $this->_receive();
        if( false === $_isReceive ){
            return false;
        }
        return [
            'code' => 'SUCCESS',
            'data' => []
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
        $this->bizContent = empty($bizContent['biz_content']) ? [] : $bizContent['biz_content'];
        $this->apiParam['biz_content'] = $this->bizContent;
    }

    public function getBizContent()
    {
        return $this->bizContent;
    }


    private function _receive() : bool
    {
        mGameGiftCode::$tableSubName = 1; // 游戏ID
        $_codeInfo = mGameGiftCode::getWhereFirstInfo(['code'=>$this->bizContent['code']],['id','giftid','code','uid','state']);
        if( empty($_codeInfo['code']) ){
            self::loggers('[SDK -> 领取礼包]礼包Code不存在：'.$this->bizContent['code'],'notice');
            self::setError('BUSINESS_FAILED','aop.unknow-gift-code','礼包不存在');
            return false;
        }

        if( $_codeInfo['state'] == 2 ){ // 已被使用
            if( $_codeInfo['uid'] == $this->_authUser->user()->id ){ // 是否被当前用户领取
                return true;
            }else{
                self::loggers('[SDK -> 领取礼包]礼包Code已被他人领取：'.$this->bizContent['code'],'notice');
                self::setError('BUSINESS_FAILED','aop.invalid-gift-code','游戏礼包已被他人领取，请刷新后重试');
                return false;
            }
        }

        // 进入领取更新
        if( mGameGiftCode::whereUpdate(['id'=>$_codeInfo['id']],[
            'uid' => $this->_authUser->user()->id,
            'state' => 2,
            'updated_at' => time()
        ]) ){
            // 领取成功
            return true;
        }else{
            self::loggers('[SDK -> 领取礼包]礼包Code领取失败：'.$this->bizContent['code'],'notice');
            self::setError('BUSINESS_FAILED','aop.invalid-gift-code','游戏礼包领取失败');
            return false;
        }
    }

}
