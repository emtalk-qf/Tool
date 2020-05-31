<?php
namespace App\Http\Sdk\Module;
use App\Http\Sdk\SdkModuleBase;
use App\Http\Sdk\SdkModuleInterface;
use Illuminate\Http\Request;
use App\Model\GameGiftModel as mGameGift;
use App\Model\GameGiftCodeModel as mGameGiftCode;

class DkDataGiftList extends SdkModuleBase implements SdkModuleInterface
{

    public $isAuthUser = true;
    public $sub_must = [];

    public function getApiMethodName() : string
    {
        return 'dk.data.gift.list';
    }

    public function businessRun(Request $request)
    {
        $data = $this->list();
        return [
            'code' => 'SUCCESS',
            'data' => [
                'data' => $data
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
        $this->bizContent = empty($bizContent['biz_content']) ? [] : $bizContent['biz_content'];
        $this->apiParam['biz_content'] = $this->bizContent;
    }

    public function getBizContent()
    {
        return $this->bizContent;
    }

    private function list()
    {
        $_page = isset($this->bizContent['page']) ? intVal($this->bizContent['page']) : 1;
        $_limit = isset($this->bizContent['limit']) ? intVal($this->bizContent['limit']) : 10;

        // ['id'=>1,'title'=>'《就叫不知道》公测礼包', 'attr'=>['剩余数量：9854'], 'describe'=>'绑元*1000，坐骑进阶丹*14，坐骑技能书*1234...','buttonName'=>'领取'],
        $this->getTokenUserInfo($this->common_param[self::getConfigs('tokenName')]);
        $_uid = $this->_authUser->user()->id;
        $_data = [];
        $_list = mGameGift::getAllList([],[$_page,$_limit],['id','name as title','content as describe','number','gid']);
        if( empty($_list) ){
            return ['list'=>[]];
        }
        foreach ($_list as $item) {
            $item['attr'] = [
                '剩余数量:'.$item['number']
            ];
            mGameGiftCode::$tableSubName = $item['gid'];
            unset($item['number'],$item['gid']);
            $_codeInfo = mGameGiftCode::getWhereFirstInfo(['giftid'=>$item['id']],['code','uid','state']);
            $item['code'] = empty($_codeInfo['code']) ? '异常' : $_codeInfo['code'];
            if( $_uid == $_codeInfo['uid'] ){
                $item['state'] = 1;
                $item['buttonName'] = '查看';
            }else{
                $item['state'] = 0;
                $item['buttonName'] = '领取';
            }
            $_data[] = $item;
            unset($_codeInfo);
        }
        return [
            'list' => $_data
        ];
    }
}
