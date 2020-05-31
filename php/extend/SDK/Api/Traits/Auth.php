<?php
namespace App\Http\Sdk\Traits;
use App\Model\SystemUserModel as mSysUser;

trait Auth
{
    # 授权Token用户信息
    protected $_authUser = [];
    # 授权字段名称
    private $_tokenName = '';

    /**
     * 授权Token验证
     *
     * @return bool
     */
    public function TokenVerify() : bool
    {
        static $TokenVerify;
        if( !isset($this->common_param[$this->_tokenName]) ){
            if( !isset($this->parameter['common'][$this->_tokenName]) ){
                return false;
            }
            $_accessToken = $this->parameter['common'][$this->_tokenName];
        }else{
            $_accessToken = $this->common_param[$this->_tokenName];
        }
        if( isset($TokenVerify[$_accessToken]) ){
            return $TokenVerify[$_accessToken];
        }
        return $TokenVerify[$_accessToken] = boolval(mSysUser::isExist($_accessToken,'api_token'));
    }

    public function getTokenUserInfo($apiToken) : void
    {
        static $_TokenUser;
        if(!empty($_TokenUser[$apiToken])){
            $this->_authUser = $_TokenUser[$apiToken];
            return;
        }
        $_user = mSysUser::getFirstInfo($apiToken,'api_token',['id']);
        if( empty($_user['id']) ){
            $this->_authUser = $_user = null;
            return;
        }
        $this->_authUser = \Illuminate\Support\Facades\Auth::guard('home');
        $_user = \App\Http\Controllers\Auth\Home\User::where('id',$_user['id'])->first();
        $this->_authUser->setUser($_user);
        $_TokenUser[$apiToken] = $this->_authUser;
        return;
    }

    public function setTokenName($token_name) : void
    {
        $this->_tokenName = $token_name;
        return;
    }

}
