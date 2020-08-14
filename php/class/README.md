# 各Class使用示例

### Enum数据调用

```php
# 枚举数据类
class EnumDataClass
{
    // 性别
    const SEX_FEMALE        = 1; // 女
    const SEX_MALE          = 2; // 男
    
    // 操作系统
    const OS_APPLE          = ['ios','苹果'];
    const OS_ANDROID        = ['android','安卓'];
}

// -----------------------调用---------------------------- //

# 常规调用示例
$_enumUnit = \Unit\EnumUnit::getIns('EnumDataClass');
$_enumUnit->setPath('\App\Enum\\'); // (可选)默认：\App\Enum\

$_enumUnit->key('SEX_FEMALE')->val(); // 结果：1
$_enumUnit->key('OS_APPLE')->val();// 结果：['ios','苹果']
$_enumUnit->key('OS_APPLE.*')->val();// 结果：['ios','苹果']
$_enumUnit->key('OS_APPLE.0')->val(); // 结果：ios

# Trait方式
use \Traits\Enum AS TraitsEnum;
class testClass
{
	use TraitsEnum;

	public function demo(){
		self::enuminit();
		return self::enumkey('OS_APPLE')->val();
	}
}
$_testClass = new testClass();
$_testClass->demo(); // ['ios','苹果']




```
