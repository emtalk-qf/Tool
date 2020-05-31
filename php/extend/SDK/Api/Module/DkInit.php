<?php
namespace App\Http\Sdk\Module;
use App\Http\Sdk\SdkModuleInterface;
use App\Http\Sdk\SdkModuleBase;
use App\Model\SystemPayChannelModel as mSysPayChannel;
use App\Model\SystemSdkRsaModel as SysSdkRsa;
use Illuminate\Http\Request;

class DkInit extends SdkModuleBase implements SdkModuleInterface
{
    public $sub_must = ['type','gkey'];
    # 内部调用参数
    private $apiParam = [];
    private $unixType = ['INIT','IOS','ANDROID'];

    public function getApiMethodName() : string
    {
        return 'dk.init';
    }

    public function businessRun(Request $request)
    {
        $_data = [];
        // 功能开关控制（需与客户端协调）
        $_data['switch']     = $this->switchinit();
        // 公共外链配置（需与客户端协调）
        $_data['publicLink'] = $this->publicLinkinit();
        // 顶部导航菜单配置
        $_data['topMenu']    = $this->topMenuinit();
        // 主页面TAB数据配置
        $_data['tabData'] = $this->tabDatainit();
        // 充值相关配置
        $_data['recharge'] = $this->payinit();
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

    # 开关配置
    private function switchinit() : array
    {
        $_config = [];
        $_config = [
            'login' => 1,
            'third_login' => -1,
            'register' => 1,
            'recharge' => 1,
        ];
        return $_config;
    }

    # 公共URL配置
    private function publicLinkinit() : array
    {
        $_config = [];
        $_config = [
            'forget_pass' => [
                'type' => 'inner',
                'url' => route('findpwd')
            ],
            'game_center' => [
                'type' => 'outside',
                'url' => route('gw_game')
            ],
        ];
        return $_config;
    }

    # 顶部菜单配置
    private function topMenuinit() : array
    {
        $_config = [];
        $_config = [
            [
                'name' => '客服',
                'title'   => '在线客服',
                'dataType' => 'ordinaryList',
                'ordinaryListData' => [
                    ['label'=>'QQ：','text'=>'2155003906'],
                    ['label'=>'电话：','text'=>'0592-5973753']
                ]
            ],
            [
                'name' => '切换账号',
                'title'   => '',
                'dataType' => 'signOut',
                'signOutData' => []
            ],
            [
                'name' => '我的游戏',
                'title'   => '我的游戏',
                'dataType' => 'gameList',
            ],
        ];
        return $_config;
    }

    # Tab选项数据配置
    private function tabDatainit() : array
    {
        $_config = [];
        $_config = [
            [
                'name' => '礼包',
                'icon'   => asset('/build/images/open.png'),
                'config' => [
                    [
                        'name'=>'使用方法',
                        'dataType' => 'textHtml',
                        'textHtmlData'=>"1.同种礼包每个帐号只能领取1次。\n2.同种礼包每个游戏角色只能激活1次。\n3.一个礼包码只能激活1次，激活后失效。"
                    ],
                    [
                        'name'=>'免费礼包',
                        'dataType' => 'tapeAttrList',
//                        'tapeAttrListData'=>[
//                            ['id'=>1,'title'=>'《就叫不知道》公测礼包', 'attr'=>['剩余数量：9854'], 'describe'=>'绑元*1000，坐骑进阶丹*14，坐骑技能书*1234...','buttonName'=>'领取'],
//                        ]
                    ]
                ],
            ],
            [
                'name' => '资讯',
                'icon'   => asset('/build/images/open.png'),
                'config' => [
                    [
                        'name'=>'热门资讯',
                        'dataType' => 'articleList',
                    ]
                ],
            ],
            [
                'name' => '更多游戏',
                'icon'   => asset('/build/images/open.png'),
                'config' => [
                    [
                        'name' => '必玩推荐',
                        'dataType' => 'gameList',
                        'gameListData' => [
                            [
                                "type"=> "inner", // 类型：inner和outside，前者调起内部浏览器，后者调起客户端浏览器
                                'icon'=>asset('/build/images/open.png'),'title'=>'我的游戏1','describe'=>'《就叫不知道》是一款真...','buttonName'=>'开始','url'=>'https://h5.xmlidao.com/game/shabake']
                        ]
                    ],
                    [
                        'name' => '全部游戏',
                        'dataType' => 'gameList',
                        'gameListData' => [
                            ["type"=> "inner",'icon'=>asset('/build/images/open.png'),'title'=>'我的游戏1','describe'=>'《就叫不知道》是一款真...','buttonName'=>'开始','url'=>'https://h5.xmlidao.com/game/shabake']
                        ]
                    ]
                ]
            ],
        ];;
        return $_config;
    }

    # 支付配置
    private function payinit() : array
    {
        $_payChannelWhere = [
            'state' => 1,
            'platform_move' => 1,
            'or' => ['name_as','platform']
        ];
        $_payChannel = mSysPayChannel::getAllList($_payChannelWhere,'',['id','name','name_as','name_ass','icon_24','defaults'],['sort','asc']);
        unset($_payChannelWhere);
        $_config = [
            'title' => '充值服务',
            'default' => 0,
            'channel' => []
        ];
        foreach ($_payChannel as $item) {
            $tampData['name'] = $item['name'];
            $tampData['value'] = $item['name_as'];
            $tampData['icon'] = asset(empty($item['icon_24'])?'/build/images/open.png':$item['icon_24']);
            $_config['channel'][] = $tampData;
        }
        unset($item,$tampData);
        return $_config;
    }

}
