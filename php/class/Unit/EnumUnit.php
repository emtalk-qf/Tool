<?php

namespace Unit;

/**
 * 组件 - 枚举类型处理组件
 *
 * @version  1.0.0
 * @author   Yunuo <ciwdream@gmail.com>
 */
class EnumUnit
{

    /**
     * 实例
     * @var EnumUnit $_ins
     */
    private static $_ins = null;

    /**
     * 枚举数据容器
     * @var array $_map
     */
    private static $_map = [];

    /**
     * 枚举类存放目录
     * @var string $_path
     */
    private $_path = '\App\Enum\\';

    /**
     * 枚举类文件名称(与类名其名)
     * @var string $_fileName
     */
    private $_fileName = '';

    private $_value = [];

    /**
     * 实例化EnumUnit
     *
     * @param string $fileName 要获取的的枚举类名称
     *
     * @return EnumUnit
     */
    public static function getIns($fileName)
    {
        if( !(self::$_ins instanceof self) ){
            self::$_ins = new self($fileName);
        }
        return self::$_ins;
    }

    /**
     * 获取枚举数据
     *
     * @param string $key      使用「.」符号从多维数组中检索值
     * @param mixed  $default  默认值，默认:null
     *
     * @return mixed
     */
    public function key($key,$default=null)
    {
        static $_static;
        if(isset($_static[$this->_fileName][$key]))return $_static[$this->_fileName][$key];

        $this->_value = $this->dataHandle(null,$key,$default);
        return $_static[$this->_fileName][$key] = self::$_ins;
    }

    public function value()
    {
        return self::$_ins->_value;
    }

    // 不建议投用
    public function pluck($key,$val)
    {
        $_newData = [];//self::$_ins->_value;
        $_newData[self::$_ins->_value[$key]] = self::$_ins->_value[$val];
        return $_newData;
    }

    /**
     * 获取ENUM枚举目录路径
     *
     * @return string
     */
    public function getPath() : string
    {
        return $this->_path;
    }

    /**
     * 设置ENUM枚举目录路径
     *
     * @param string $path 枚举类存在目录路径
     *
     * @return void
     */
    public function setPath($path) : void
    {
        $this->_path = $path;
    }

    private function __construct($fileName)
    {
        $this->_initEnum($fileName);
    }

    /**
     * 初始化目标枚举类
     *
     * @param static $fileName 要获取的的枚举类名称
     *
     * @return void
     */
    private function _initEnum($fileName) : void
    {
        $_path = $this->getPath().$fileName;
        $this->_fileName = $fileName;
        $this->setPath($_path);

        $_reflection = new \ReflectionClass($_path);
        $_constants = $_reflection->getConstants();
        self::$_map[$fileName] = $_constants;
    }

    /**
     * 枚举数据提取
     *
     * @param array  $data     待处理数据
     * @param string $key      使用「.」符号从多维数组中检索值
     * @param mixed  $default  默认值，默认:null
     *
     * @return EnumUnit
     */
    private function dataHandle($data,$key=null,$default=null)
    {
        $key = is_array($key) ? $key : explode('.', $key);
        if( empty($key) ){return $data;}

        $_enumData = empty($data) ? self::$_map[self::$_ins->_fileName]:$data;

        while ( !is_null($segment = array_shift($key)) )
        {
            $segment = '' === $segment ? '*' : $segment;
            if( $segment === '*' ){
                if ( !is_array($_enumData) ) {
                    return $default;
                }
                $result = [];
                foreach ($_enumData as $item) {
                    $result[] = self::dataHandle($item,$key,$default);
                }
                return in_array('*', $key) ? array_merge($result) : $result;
            }
            if( array_key_exists($segment, $_enumData)  ){
                $_enumData = $_enumData[$segment];
            }else {
                return $default;
            }
        }
        return $_enumData;
    }

    final private function __clone()
    {
    }

}


