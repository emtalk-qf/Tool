<?php
namespace App\Http\Sdk\Module;
use App\Http\Sdk\SdkModuleBase;
use App\Model\GameModel as mGame;
use App\Model\LogGameLoginModel as mLogGameLogin;
use App\Http\Sdk\SdkModuleInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DkDataGameList extends SdkModuleBase implements SdkModuleInterface
{
    public $isAuthUser = true;
    public $sub_must = [
        'position' => [
            'rule'=>'in:user',
            'tips'=>[
                'in' => '类型暂不可用'
            ]
        ],
    ];

    public function getApiMethodName() : string
    {
        return 'dk.data.game.list';
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
        $this->bizContent = $bizContent['biz_content'];
        $this->apiParam['biz_content'] = $bizContent['biz_content'];
    }

    public function getBizContent()
    {
        return $this->bizContent;
    }
    /**
     * 初始化
     */
//    private function _HandleInit() : void
//    {
//        if ($this->bizContent['type'] == self::$type[0]){
//            $this->bizContent['type'] = self::$type[0];
//        }else{
//            $this->bizContent['type'] = self::$type[1];
//        }
//    }

    /**
     * 列表
     * @return $page   页数
     * @return $limit  每页数量
     */
    private function list() : array
    {
        $_page = isset($this->bizContent['page']) ? intVal($this->bizContent['page']) : 1;
        $_limit = isset($this->bizContent['limit']) ? intVal($this->bizContent['limit']) : 10;
        $_position = $this->bizContent['position'];
        $this->getTokenUserInfo($this->common_param[self::getConfigs('tokenName')]);

        $_fun = $_position.'List';
        return $this->$_fun($_page,$_limit);
    }

    private function userList($page,$limit) : array
    {
        $_list = [];
        $_m = new mLogGameLogin();
        $_logGameList = $_m->where(['uid'=>$this->_authUser->user()->id])->select('gid')->distinct('gid')->get()->toArray();
        if( empty($_logGameList) ){
            return [
                'userList' => []
            ];
        }
        $_logGameList = Arr::pluck($_logGameList,'gid');
        $_logGameList = mGame::getAllList([['id','in',$_logGameList],'state'=>1,'isdebug'=>0],[$page,$limit],['icon','name as title','intro as describe','alias']);
        foreach ($_logGameList AS $i=>$item)
        {
            $item['type'] = 'inner'; // 类型：inner和outside，前者调起内部浏览器，后者调起客户端浏览器
            $item['icon'] = asset(empty($item['icon'])?'/build/images/open.png':$item['icon']);
            $item['url'] = route('pg_gameIndex',['gkey'=>$item['alias']]);
            $item['buttonName'] = '开始';
            unset($item['alias']);
            $_list[] = $item;
        }
        return [
            'userList' => $_list
        ];
    }
}
