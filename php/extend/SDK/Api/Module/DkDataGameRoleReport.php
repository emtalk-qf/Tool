<?php
namespace App\Http\Sdk\Module;
use App\Http\Sdk\SdkModuleBase;
use App\Http\Sdk\SdkModuleInterface;
use App\Model\GameDistrictModel as mGameDistrict;
use Illuminate\Http\Request;
use App\Model\GameModel as mGame;
use App\Model\GameRoleModel as mGameRole;

class DkDataGameRoleReport extends SdkModuleBase implements SdkModuleInterface
{
    public $isAuthUser = true;
    /**
     * 角色信息上报
     *
     * @param string  server 服务器编号，默认为0
     * @param string  roleId 角色id，默认为0
     * @param integer isNew 是否当前新创建角色，否为0，是为1
     * @param string  roleName 角色名，默认为''
     * @param integer level 等级，默认为1
     * @param integer isVip 是否是VIP，否为0，是为1
     */
    public $sub_must = [
        'server',
        'roleId',
        'isNew' => [
            'rule'=>'boolean',
            'tips'=>[
                'boolean' => '该仅限0和1'
            ]
        ],
        'roleName',
        'level' => [
            'rule'=>'integer',
            'tips'=>[
                'integer' => '角色等级必须为整数'
            ]
        ],
        'isVip' => [
            'rule'=>'in:0,1',
            'tips'=>[
                'in' => '该仅限0和1'
            ]
        ],
    ];
    private $_gameInfo = [];

    public function getApiMethodName() : string
    {
        return 'dk.data.game.role.report';
    }

    public function businessRun(Request $request)
    {
        $_isRoleReport = $this->roleReport();
        if( false === $_isRoleReport ){
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
     * 角色信息上报入库/更新
     * @return $page   页数
     * @return $limit  每页数量
     */
    private function roleReport() : bool
    {
        $this->getTokenUserInfo($this->common_param[self::getConfigs('tokenName')]);

        // 查询游戏信息
        $_gameInfo = mGame::getFirstInfo($this->apiParam['common_param']['gkey'],'alias',['id']);
        // 查询区服信息
        $_districtInfo = mGameDistrict::getWhereFirstInfo(['gid'=>$_gameInfo['id'],'districtid'=>intval($this->bizContent['server'])],['id']);
        if( !isset($_districtInfo['id']) ){
            self::loggers('[SDK -> 角色信息上报]$gkey：'.$this->apiParam['common_param']['gkey'].' $skey:'.$this->bizContent['server'],'notice');
            self::setError('BUSINESS_FAILED','aop.unknow-game-district','区服不存在或未配置，请联系管理员！');
            return false;
        }
        $_roleData = [
            'gameid' => $_gameInfo['id'], // 游戏主键ID
            'serverid' => $_districtInfo['id'], // 区服主键ID
            'uid' => $this->_authUser->user()->id, // 平台用户ID
            'level' => intval($this->bizContent['level']), // 角色等级
            'last_login_time' => time(), // 角色登录时间
            'qid' => $this->bizContent['roleId'], // 游戏角色ID
        ];

        if( !empty($this->bizContent['roleName']) ){
            $_roleData['name'] = $this->bizContent['roleName']; // 游戏角色名称
        }
        self::loggers('[SDK -> 角色信息上报]：'.http_build_query($this->apiParam));
        // 判断角色信息是否存在
        $_isRoleWhere = [
            'gameid' => $_gameInfo['id'], // 游戏主键ID
            'serverid' => $_districtInfo['id'], // 区服主键ID
            'uid' => $this->_authUser->user()->id, // 平台用户ID
        ];
        $_isRole = mGameRole::getWhereFirstInfo($_isRoleWhere,['id']);
        if( !isset($_isRole['id']) ){ // 角色信息不存在
            if( mGameRole::add($_roleData) ){
                return true;
            }else{
                self::loggers('[SDK -> 角色信息上报]游戏角色信息更新失败：'.$_roleData['name'],'notice');
                self::setError('BUSINESS_FAILED','aop.unknow-game-role','游戏角色信息更新失败！');
                return false;
            }
        }else{ // 角色信息已存在
            if( mGameRole::whereUpdate(['id'=>$_isRole['id']],$_roleData) ){
                return true;
            }else{
                self::loggers('[SDK -> 角色信息上报]游戏角色信息更新失败：'.$_roleData['name'],'notice');
                self::setError('BUSINESS_FAILED','aop.unknow-game-role','游戏角色信息更新失败！');
                return false;
            }
        }
    }

}
