<?php
namespace App\Sdk;
use App\Http\Controllers\BaseController AS Controller;
use App\Sdk\SdkAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Arr;
use App\Sdk\Unit\Fun;
use App\Sdk\Traits\Handler AS SdkHandler;
use App\Sdk\Traits\Config AS SdkConfig;
use App\Sdk\Traits\Log AS SdkLog;

/**
 * SDK相关管理控制器 SDK API网关调度类
 *
 * @version  1.0.0
 * @author   Yunuo <ciwdream@gmail.com>
 */
class GatewayController extends Controller
{
    use SdkHandler,SdkConfig,
        SdkLog;

    # 是否为开发模式(根据.env内配置自动变化)
    private $app_debug = false;
    # 客户端应用ID
    private $app_id;
    # 接口名称(白名单列表)
    private $method    = [];
    # 响应返回支持格式列表
    private $format    = ['JSON'];
    # 请求使用的编码
    private $charset   = ['UTF-8','GBK'];
    # 签名算法类型支持列表
    private $sign_type = ['RSA2','MD5'];
    # 调用的接口版本
    private $version   = '1.0';
    # 授权字段名称
    private $token_name = 'access_token';
    # 公共调用参数(公共必填参数,Token字段给名称会自动追加)
    private $common_param = ['app_id', 'method','format','charset','sign_type','sign','timestamp','version','channel','gkey'];
    # 内部调用参数
    private $parameter = [];
    # 模块路径
    private $_modulePath = '';
    # 接口白名单缓存时间（秒/s） 生产环境为604800
    private $_methodCacheTime = 604800;
    # SDK
    private $_sdkAuthInfo = [];

    public function __construct()
    {
        parent::__construct();
        $this->_inits();
    }

    public function run(Request $request) : array
    {
        # 请求拦截策略检测
        if(!$this->restrictiveStrategy())return $this->apiReturn('INVALID_TOKEN',[],'isp.service-limit');
        # 请求参数安检
        $_isIniParam = $this->initParameter($request);
        if(!empty($_isIniParam))return $_isIniParam;
        unset($_isIniParam);
        # 调起子模块运行
        return $this->initAppRun($request,$this->parameter['common']['method']);
    }

    public function viewRun(Request $request)
    {
        # 请求拦截策略检测
        if(!$this->restrictiveStrategy())return $this->apiReturn('INVALID_TOKEN',[],'isp.service-limit');
        app('view')->prependNamespace('sdk',app_path('Sdk/views'));
        $_type = $request->input('type',null);
        if( $_type == 'pay_view' ){
            $_code = $request->input('code',null);
            if( empty(trim($_code)) ){
                return $this->apiReturn();
            }
            $_cacheName = 'SDK_ViewCode_'.$_code;
            if( !Redis::exists($_cacheName) ){
                return $this->apiReturn();
            }
            $_cacheData = Redis::get($_cacheName);
        }elseif($_type == 'openinstall'){ // openinstall - 高效的App推广渠道统计;https://www.openinstall.io/
            return view('sdk::openinstall');
        }else{
            return $this->apiReturn();
        }

        $_viewVar = [];
        if( Fun::isJson($_cacheData) ){
            $_viewVar['type'] = 'json';
            $_viewVar = array_merge($_viewVar,json_decode($_cacheData,true));
        }elseif( strpos($_cacheData,'http') === 0 ){
            return redirect()->away($_cacheData);
        }else{
            $_viewVar['type'] = 'html';
            $_viewVar['data'] = $_cacheData;
        }
        return view('sdk::viewRun',$_viewVar);
    }

    private function _inits()
    {
        # 初始化模块位置
        $this->_modulePath = '\\'.str_replace('GatewayController','Module',__CLASS__).'\\';

        # 调试模式，执行run方法会根据授权APPID具体配置信息进行动态调整
        $this->app_debug = env('APP_DEBUG',false);
        # 可用白名单接口构建初始化
        $this->_usableModuleList();
        $this->setTokenName($this->token_name);
        self::setConfigs('tokenName',$this->token_name);
    }

    /**
     * initAppRun 初始化模块类
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @param string $method
     *
     * @return  array
     */
    private function initAppRun(Request $request,$method) : array
    {
        $_class = $this->getMethodClass($method);
        if( empty($_class) ){
            self::logger('[SDK -> initAppRun]子模块：'.$_class.'实例化异常，错误：'.$_class.'类不存在','error');
            return $this->apiReturn('UNKNOW_ERROR',[],'isp.service-error');
        }
        // 实例化子模块类
        $_app = \App::make($_class);
        // 通知子模块，当前调试模式
        $_app->thisIsDebug = $this->app_debug;
        // 验证模块加载是否一致
        if( $method !== $_app->getApiMethodName() ){
            self::logger('[SDK -> initAppRun]调用子模块：'.$_class.'->'.$method.'，错误：'.var_export($_app->getApiMethodName(),true),'notice');
            return $this->apiReturn('UNKNOW_ERROR',[],'isp.service-error');
        }

        // 是否强制验证Token，则验证授权信息是否正确
        if( true === $_app->isAuthUser ){
            if( !isset($this->parameter['common'][$this->token_name]) ){
                self::loggers('[SDK -> initAppRun]Token信息不存在','notice');
                return $this->apiReturn('INVALID_TOKEN',[],'isv.invalid-auth-token','请填写有效的Token');
            }
            if( $this->TokenVerify() === false ){
                self::loggers('[SDK -> initParameter]无效的Token：'.var_export($this->TokenVerify(),true),'notice');
                return $this->apiReturn('INVALID_TOKEN',[],'isv.invalid-auth-token','无效的Token');
            }
            $_app->getTokenUserInfo($this->parameter['common'][$this->token_name]);
        }

        // 验证必填字段是否传参
        if( !empty($_app->sub_must) ){
            if( !$this->_subMustParameter($_app->sub_must) ){
                if( !self::isError() ){
                    self::$Handler = $_app->_handler();
                }
                self::logger('[SDK -> initAppRun]验证必填字段是否传参，子模块：'.get_class($_app).'运行触发异常，模块内错误：'.var_export(self::$Handler,true),'notice');
                return $this->apiReturn();
            }
        }

        // 为子模块赋外部传参
        $_app->setCommonParam($this->parameter['common']);
        if( !empty($this->parameter['business']) ){
            if(!$_app->setBizContent($this->parameter['business'])){
                self::$Handler = $_app->_handler();
                self::logger('[SDK -> initAppRun]为子模块赋外部传参，子模块：'.get_class($_app).'运行触发异常，模块内错误：'.var_export(self::$Handler,true),'notice');
                return $this->apiReturn();
            }
        }

        $_app->sdkinfo = $this->_sdkAuthInfo;

        // 等待子模块处理结果
        try {
            $_rs = $_app->businessRun($request);
        }catch (\Exception $e){
            $_errer = $e->getFile().':'.$e->getLine().' '.$e->getMessage();
            self::logger('[SDK -> initAppRun]子模块：'.get_class($_app).'运行触发异常，错误：'.$_errer,'error');
            return $this->apiReturn('UNKNOW_ERROR',[],'isp.service-error','运行异常，请联系管理员');
        }

        // 对子模块返回结果进行处理
        if( !is_array($_rs) ){
            self::$Handler = $_app->_handler();
            self::logger('[SDK -> initAppRun]子模块：'.get_class($_app).'运行触发异常，模块内错误：'.var_export(self::$Handler,true),'notice');
            return $this->apiReturn();
        }
        if( $_rs['code'] != 'SUCCESS' ){
            return $this->apiReturn($_rs['code'],[],$_rs['sub_code'],$_rs['sub_msg']);
        }else{
            $_rs['sub_code'] = isset($_rs['sub_code']) ? $_rs['sub_code'] : '';
            $_rs['sub_msg'] = isset($_rs['sub_msg']) ? $_rs['sub_msg'] : '';
            return $this->apiReturn($_rs['code'],$_rs['data'],$_rs['sub_code'],$_rs['sub_msg']);
        }
    }

    /**
     * iniParameter 初始化请求参数
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @param Request $request
     *
     * @return  array
     */
    private function initParameter(Request $request)
    {
        $this->_parameterOneForm($request);
        # 检查公共请求参数
        $_isHas = '';
        if (!$request->filled('app_id')) { // APPID应用授权ID
            $_isHas = 'app_id';
        }elseif (!$request->filled('method')) { // 接口指向
            $_isHas = 'method';
        }elseif (!$request->filled('sign_type')) { // 校验方式
            $_isHas = 'sign_type';
        }elseif (!$request->filled('sign')) { // 校验值
            $_isHas = 'sign';
        }elseif (!$request->filled('timestamp')) { // 请求时间
            $_isHas = 'timestamp';
        }elseif (!$request->filled('version')) { // SDK版本
            $_isHas = 'version';
        }elseif (!$request->filled('channel')) { // 渠道标识
            $_isHas = 'channel';
        }elseif (!$request->filled('gkey')) { // 游戏标识
            $_isHas = 'gkey';
        }
        if( !empty($_isHas) )
            return $this->apiReturn('MISSING_SIGNATURE',[],'isv.missing-'.str_replace('_','-',$_isHas),'检查请求参数，缺少'.$_isHas.'参数');
        unset($_isHas);

        # 检查请求处理接口
        $_data = $request->input('method',null);
        if( !isset($this->method[$_data]) ){
            return $this->apiReturn('INVALID_PARAMETER',[],'isv.invalid-method','检查入参method是否正确');
        }
        unset($_data);

        # 检查数据格式
        $_data = $request->input('format',null);
        if( !in_array($_data,$this->format) ){
            return $this->apiReturn('INVALID_PARAMETER',[],'isv.invalid-format','检查入参format，目前只支持json和xml 2种格式');
        }
        unset($_data);

        # 检查签名类型
        $_data = $request->input('sign_type',null);
        if( !in_array($_data,$this->sign_type) ){
            return $this->apiReturn('INVALID_PARAMETER',[],'isv.invalid-signature-type','检查入参sign_type,目前只支持RSA2');
        }
        unset($_data);

        # 检查字符集
        $_data = $request->input('charset',null);
        if( !in_array($_data,$this->charset) ){
            return $this->apiReturn('INVALID_PARAMETER',[],'isv.invalid-charset','请求参数charset错误，目前支持格式：GBK,UTF-8');
        }
        unset($_data);

        // 初始化SdkAuth实例
        $this->app_id = $request->input('app_id',null);
        SdkAuth::getIns($this->app_id);

        # 检查签名
        if( !SdkAuth::verificationSign($request->all()) ){
            self::loggers('[SDK -> 初始化参数]校验未通过','warning');
            return $this->apiReturn('INVALID_PARAMETER',[],'isv.invalid-signature','无效签名');
        }
        $request->offsetUnset('sign');

        # 检查APPID
        if( SdkAuth::appidVerify() ){
            $this->app_debug = boolval(SdkAuth::getAuthInfo()->is_debug);
        }else{
            return $this->apiReturn('INVALID_PARAMETER',[],'isv.invalid-app-id','检查入参app_id，app_id不存在，或者未上线');
        }

        # 检查游戏授权许可
        $_data = $request->input('gkey',null);
        if( !SdkAuth::gameAuthVerify($_data) ){
            return $this->apiReturn('INVALID_TOKEN',[],'isv.invalid-gkey','授权游戏暂未开放或不存在，请联系相关员了解');
        }
        unset($_data);

        $this->_sdkAuthInfo['sdkAuth'] = SdkAuth::getAuthInfo();

        # 公共请求参数
        $this->parameter['common'] = $request->only(array_merge($this->common_param,[$this->token_name]));

        # 业务请求参数
        $this->parameter['business']  = Arr::where($request->all(),function ($value, $key) {
            return !isset($this->parameter['common'][$key]);
        });
        if( !empty($this->parameter['business']['biz_content']) ){
            $this->parameter['business']['biz_content'] = rawurldecode($this->parameter['business']['biz_content']);
            if( Fun::isJson($this->parameter['business']['biz_content']) ){
                $this->parameter['business']['biz_content'] = json_decode($this->parameter['business']['biz_content'],true);
            }
        }
        $this->logger('[SDK -> 初始化参数]$parameter：'.var_export($this->parameter,true));
        return [];
    }

    /**
     * apiReturn API统一响应调用方法
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @param string $code      // API公共错误码
     * @param string $sub_code  // API业务错误码(明细返回码)
     * @param string $sub_msg   // API业务错误码(明细返回码描述)
     *
     * @return  array
     */
    private function apiReturn($code='',array $data=[],$sub_code='',$sub_msg='') : array
    {
        if( empty($code) && empty($data) && empty($sub_code) && empty($sub_msg) ){
            extract(self::getError());
        }
        $_rs = [];
        $_code = Fun::getEnum('CODE.'.$code);
        try{
            $_rs['code'] = $_code[0];
            $_rs['msg'] = $_code[1];
        }catch (\Exception $e)
        {
            $_code = Fun::getEnum('CODE.INVALID_PARAMETER');
            $_rs['code'] = $_code[0];
            $_rs['msg'] = $_code[1];
        }
        unset($_code);
        if( !empty($sub_code) ){
            $_rs['sub_code'] = $sub_code;
            $_rs['sub_msg']  = $sub_msg;
        }
        if( !empty($data) ){
            $_rs = array_merge($_rs,$data);
        }
        return $_rs;
    }

    /**
     * restrictiveStrategy API请求拦截策略
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @return  bool
     */
    private function restrictiveStrategy() : bool
    {
        return true;
    }

    /**
     * isFieldRxist API传参判断必填参数是否填写
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @param array  $must  需要验证的必填字段(一维数组)
     * @param array  $data  可传入参数进行数据验证
     *
     * @return  array
     */
    private function isFieldRxist(array $must = [],array $data = [])
    {
        if( empty($data) ){
            return [];
        }
        # 判断必填项是否存在
        $must = !empty($must) ? $must : ['sign'];
        foreach ($must AS $field => $value)
        {
            if( is_string($field) ){
                if( !$this->_formValidator($field,$value) ){
                    return $field;
                }
            }else{
                if( !isset($data[ $value ]) ||
                    (empty($data[ $value ]) && !is_numeric($data[ $value ])) ){
                    self::setError('MISSING_SIGNATURE','isv.missing-'.str_replace('_','-',$value),'检查请求参数，缺少'.$value.'参数或参数值不正确');
                    return $value;
                }
            }
        }
        return $data;
    }

    /**
     * getMethodClass 获取白名单接口对应类路径(命名空间路径)
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @param string  $name   接口名称
     *
     * @return  string
     */
    private function getMethodClass($name) : string
    {
        return isset($this->method[$name]) ? $this->method[$name] : '';
    }

    /**
     * _parameterOneForm 对外部数据类型进行格式化
     *
     * 统一成内部Illuminate\Http\Request->input()数据
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @param Request $request
     *
     * @return  void
     */
    private function _parameterOneForm(Request $request) : void
    {
        $_content = $request->getContent();
        if( Fun::isJson($_content) ){
            $_tampData = json_decode($_content,true);
            foreach ($_tampData AS $name => $value)
            {
                $request->offsetSet($name,$value);
            }
            unset($_tampData,$name,$value);
            $request->offsetUnset($_content);
        }else{
            $_tampData = $request->all();
            foreach ($_tampData AS $name => $value)
            {
                $name  = trim($name);
                $value = trim($value);
                if( empty($value) || empty($name) ){
                    $request->offsetUnset($name);
                }
            }
        }
        return;
    }

    /**
     * _subMustParameter 对子模块必填传参进行验证
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @param array $must // 待验证字段集(索引数组)
     *
     * @return  bool
     */
    private function _subMustParameter(array $must) : bool
    {
        if( array_key_exists('business',$this->parameter) && !empty($this->parameter['business']) ){
            $_isFieldRxist = $this->isFieldRxist($must,$this->parameter['business']['biz_content']);
        }else{
            $_isFieldRxist = $this->isFieldRxist($must,$this->parameter['common']);
        }
        if( is_string($_isFieldRxist) ){
            if( !self::isError() ){
                self::setError('MISSING_SIGNATURE','isv.missing-'.str_replace('_','-',$_isFieldRxist),'检查请求参数，缺少'.$_isFieldRxist.'参数或参数值不正确');
            }
            return false;
        }
        unset($_isFieldRxist);
        return true;
    }

    /**
     * _formValidator API传参通过表单验证机制验证
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @param string  $field   需要验证的必填字段名称
     * @param array   $config  表单验证规则\Illuminate\Contracts\Validation\Validator|\Illuminate\Contracts\Validation\Factory
     * 变量规则：
     * 'rule'=>'', // laravel表单验证规则语法，参考https://learnku.com/docs/laravel/6.x/validation/5144#available-validation-rules
     * 'tips'=>[]  // 对应规则的提示信息
     *
     * 示例：
     * [
     *     'rule'=>'alpha|array',
     *     'tips'=>[
     *         'alpha' => '该字段必须完全由字母构成',
     *         'array' => '该字段为数组数据'
     *     ]
     * ]
     *
     *
     * @return  array
     */
    private function _formValidator($field,$config) : bool
    {
        if( isset($this->parameter['business']['biz_content']) ){
            $_value = isset($this->parameter['business']['biz_content'][$field]) ? $this->parameter['business']['biz_content'][$field] : null;
        }else{
            $_value = isset($this->parameter['common'][$field]) ? $this->parameter['common'][$field] : null;
        }
        $messages = [];
        if( !isset($config['rule']) ){
            $config['rule'] = '';
        }
        if( is_array($config['rule']) ){
            $config['rule'] = implode('|',$config['rule']);
        }
        if( strpos($config['rule'],'|') > 0 ){
            $_tampData = explode('|',$config['rule']);
            foreach ($_tampData AS $item)
            {
                if( strpos($item,':') ){
                    $item = explode(':',$item);
                    $item = $item[0];
                }
                if( isset($config['tips'][$item]) ){
                    $messages[$field.'.'.$item] = $config['tips'][$item];
                }
            }
            unset($_tampData,$item);
        }else{
            if( !empty($config['rule']) ){
                $_tampData = $config['rule'];
                if( strpos($_tampData,':') ){
                    $_tampData = explode(':',$_tampData);
                    $_tampData = $_tampData[0];
                }
                if( isset($config['tips'][$_tampData]) ){
                    $messages[$field.'.'.$_tampData] = $config['tips'][$_tampData];
                }
                unset($_tampData);
            }else{
                if( !empty($config['tips']['required']) ){
                    $messages[$field.'.required'] = $config['tips']['required'];
                }
            }
        }
        $_validator = validator([$field=>$_value],[$field=>'required'.(empty($config['rule'])?'':'|'.$config['rule'])], $messages);
        if( true === $_validator->fails() ){
            $_msg = $_validator->errors()->messages();
            $_skey = key($_msg);
            self::setError('MISSING_SIGNATURE','isv.missing-'.str_replace('_','-',$_skey),'检查请求参数'.$_skey.'，'.$_msg[$_skey][0]);
            return false;
        }else{
            return true;
        }
    }

    /**
     * _usableModuleList 构建可用白名单接口名单
     *
     * @access  private
     * @version 1.0.0
     * @author  Yunuo <ciwdream@gmail.com>
     *
     * @return  void
     */
    private function _usableModuleList() : void
    {
        $_cacheName = 'SDK_ModuleList_WhiteList';
        if( !Redis::exists($_cacheName) ){
            $_modulePath = app_path(substr(str_replace('\\','/',$this->_modulePath),4));
            config(['filesystems.disks.sdk_module'=>[
                'driver' => 'local',
                'root' => $_modulePath
            ]]);
            $disk = \Illuminate\Support\Facades\Storage::disk('sdk_module');
            $_filesList = $disk->files();
            foreach ($_filesList AS $fileName)
            {
                $_class = substr(preg_replace_callback('/([A-Z])/',function($matches){
                    return '.'.strtolower($matches[1]);
                },basename($fileName,".php")),1);
                $this->method[$_class] = $this->_modulePath.basename($fileName,".php");
                $this->app_debug ? null : Redis::setex($_cacheName,$this->_methodCacheTime,json_encode($this->method));
            }
        }else{
            $this->method = json_decode(Redis::get($_cacheName),true);
        }
        return;
    }
}
