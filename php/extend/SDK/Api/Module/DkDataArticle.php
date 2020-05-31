<?php
namespace App\Http\Sdk\Module;
use App\Http\Sdk\SdkModuleBase;
use App\Http\Sdk\SdkModuleInterface;
use App\Model\SystemArticleBodyModel as mSysArticleBody;
use App\Model\SystemArticleModel as mSysArticle;
use Illuminate\Http\Request;

class DkDataArticle extends SdkModuleBase implements SdkModuleInterface
{
    public $isAuthUser = true;
    public $sub_must = [
        'type' => [
            'rule'=>'in:details,list',
            'tips'=>[
                'in' => '类型暂不可用'
            ]
        ],
    ];
    private static $type = ['details','list'];

    public function getApiMethodName() : string
    {
        return 'dk.data.article';
    }

    public function businessRun(Request $request)
    {
        $this->_HandleInit();
        if(self::isError())return false;

        if ($this->bizContent['type'] == self::$type[0]){ // 文章详情内容获取
            $data =  $this->details();
        }else{ // 文章分页列表获取
            $data = [
                'dataType' => 'articleList',
                'articleListData' => $this->list()
            ];
        }
        return [
            'code' => 'SUCCESS',
            'data' => $data
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
    private function _HandleInit() : void
    {
    }

    /**
     * 列表
     * @return $page   页数
     * @return $limit  每页数量
     */
    private function list() : array
    {
        $_page  = isset($this->bizContent['page']) ? intVal($this->bizContent['page']) : 1;
        $_limit = isset($this->bizContent['limit']) ? intVal($this->bizContent['limit']) : 10;

        $_where = [];

        $_list  = mSysArticle::getAllList($_where,[$_page,$_limit],['id','title','class','jump','created_at']);
        foreach ($_list as $i => $item) {
            switch ($item['class']){
                case '1':
                    $typeName = '公告';
                    break;
                case '2':
                    $typeName = '新闻';
                    break;
                case '3':
                    $typeName = '活动';
                    break;
                case '4':
                    $typeName = '攻略';
                    break;
            }
            $item['noticeLabel'] = $typeName;
            $item['date'] = date('m-d',strtotime($item['created_at']));
            $item['jump'] = empty($item['jump']) ? '' : $item['jump'];
            unset($item['class'],$item['created_at']);
            $_list[$i] = $item;
        }
        return $_list;
    }

    /**
     * 文章详情
     * @return $aid   文章id
     */
    private function details() : array
    {
        $aid = intval($this->bizContent['aid']);
        if ($aid <= 0){
            self::setError('UNKNOW_ERROR','isp.unknow-article-id','无效或文章不存在');
            return [];
        }
        // 获取文章标题
        $_articleInfo = mSysArticle::getFirstInfo($aid,'id',['title','updated_at as date','bodyid']);
        if( !isset($_articleInfo['bodyid']) ){
            self::setError('UNKNOW_ERROR','isp.unknow-article-id','无效或文章不存在');
            return [];
        }
        $_articleInfo['attribute'] = [
            [
                'label' => '更新时间',
                'text' => date('Y-m-d H:i',$_articleInfo['date'])
            ]
        ];
        unset($_articleInfo['date']);

        // 获取文章内容
        $_articleBody = mSysArticleBody::getFirstInfo($_articleInfo['bodyid'],'id',['body']);
        if( !isset($_articleBody['body']) ){
            $_articleBody['body'] = '暂无文章内容';
        }
        $_articleInfo['body'] = $_articleBody['body'];
        unset($_articleInfo['bodyid'],$_articleInfo['jump']);
        return $_articleInfo;
    }

}
