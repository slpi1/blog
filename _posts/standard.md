---
title: PHP代码规范
date: 2019-10-16 19:19
tags:
- PHP
---

## 1. 文件夹
- 文件夹名称**必须**符合 `CamelCase` 式的大写字母开头驼峰命名规范。

## 2. 文件
- `PHP` 代码文件**必须**以不带 `BOM` 的 `UTF-8` 编码。
- 纯 `PHP` 代码文件**必须**省略最后的 `?>` 结束标签。

## 3. 行
- 行的长度一定限制在140个字符以内。
- 非空行后一定不能有多余的空格符。
- 每行一定不能存在多于一条语句。
- 适当空行可以使得阅读代码更加方便以及有助于代码的分块（但注意不能滥用空行）。

## 4. 缩进
- 代码**必须**使用4个空格符的缩进，一定不能用 `tab` 键 。

## 5. 关键字以及 `true/false/null`
- `PHP` 所有关键字**必须**全部小写。
- 常量 `true`、`false` 和 `null` **必须**全部小写。

## 6. `namespace` 以及 `use` 声明
- `namespace` 声明后**必须**插入一个空白行。
- 所有 `use` **必须**在 `namespace` 后声明。
- 每条 `use` 声明语句**必须**只有一个 `use` 关键词。
- `use` 声明语句块后**必须**要有一个空白行。


```php
<?php 

namespace VendorgiPackage;  

use FooClass; 
use BarClass as Bar; 
use OtherVendorgiOtherPackage\BazClass;  

// ... additional PHP code ...

```

## 7. 类 `class`，属性 `properties` 和方法 `methods`
这里的类是广义的类，它包含所有的类 `classes` ，接口 `interface` 和 `traits`。

### 7.1 类 `class`
- 类的命名**必须**遵循大写字母开头的驼峰式命名规范。

```php
<?php 

namespace VendorgiPackage;  

class ClassName 
{
    // constants, properties, methods
}

```

### 7.2 `extends` 和 `implements`
- 关键词 `extends` 和 `implements` **必须**写在类名称的同一行。
- 类的开始花括号**必须**独占一行，结束花括号也**必须**在类主体后独占一行。

```php
<?php 

namespace VendorgiPackage;  

use FooClass; 
use BarClass as Bar; 
use OtherVendorgiOtherPackage\BazClass;  

class ClassName extends ParentClass implements giArrayAccess, \Countable 
{
    // constants, properties, methods 
}

```

- `implements` 的继承列表如果超出140个字符也可以分成多行，这样的话，每个继承接口名称都**必须**分开独立成行，包括第一个。

```php
<?php 

namespace VendorgiPackage;  

use FooClass; 
use BarClass as Bar; 
use OtherVendorgiOtherPackage\BazClass;  

class ClassName extends ParentClass implements     
    giArrayAccess,     
    giCountable,     
    giSerializable 
{
     // constants, properties, methods 
}

```

### 7.3 常量 `const`
- 类的常量中所有字母都**必须**大写，词间以下划线分隔。 

```php
<?php 

namespace VendorgiPackage;  

use FooClass; 
use BarClass as Bar; 
use OtherVendorgiOtherPackage\BazClass;  

class ClassName extends ParentClass implements giArrayAccess, \Countable 
{
    const VSESION  = '1.0';
    const SITE_URL = 'http://www.xxx.com '; 
}

```

### 7.4  属性 `properties`
- 类的属性命名**必须**遵循小写字母开头的驼峰式命名规范 `$camelCase`。
- **必须**对所有属性设置访问控制（如，`public`，`protect`，`private`）。
- 一定不可使用关键字 `var` 声明一个属性。
- 每条语句一定不可定义超过一个属性。
- 不要使用下划线作为前缀，来区分属性是 `protected` 或 `private`。
- 定义属性时先常量属性再变量属性，先 `public` 然后 `protected`，最后 `private`。

以下是属性声明的一个范例：

```php
<?php 

namespace VendorgiPackage;  

class ClassName 
{
    const VSESION = '1.0';
    public $foo = null;
    protected $sex;
    private $name; 
}

```

### 7.5 方法 `methods`
- 方法名称**必须**符合 `camelCase()` 式的小写字母开头驼峰命名规范。
- 所有方法都**必须**设置访问控制（如，`public`，`protect`，`private`）。
- 不要使用下划线作为前缀，来区分方法是 `protected` 或 `private`。
- 方法名称后一定不能有空格符，其开始花括号独占一行，结束花括号**必须**在方法主体后单独成一行。参数左括号后和右括号前一定不能有空格。
- 一个标准的方法声明可参照以下范例，留意其括号、逗号、空格以及花括号的位置。

```php
<?php 

namespace VendorgiPackage;

class ClassName 
{
    public function fooBarBaz($storeName, $storeId, array $info = [])
    {
        // method body     
    } 
}

```

### 7.6 方法的参数 `method arguments`
- 方法参数名称**必须**符合 `camelCase` 式的小写字母开头驼峰命名规范
- 参数列表中，每个参数后面**必须**要有一个空格，而前面一定不能有空格。
- 有默认值的参数，必须放到参数列表的末尾。
- 如果参数类型为对像**必须**指定参数类型为具体的类名，如下的 `$bazObj` 参数。
- 如果参数类型为 `array` **必须**指定参数类型为 `array` 。如下 `$info`。

```php
<?php 

namespace VendorgiPackage;  

class ClassName 
{    
    public function foo($storeName, $storeId, BazClass $bazObj, array $info = [])
    {
        // method body     
    }
}

```

- 参数列表超过140个字符可以分列成多行，这样，包括第一个参数在内的每个参数都**必须**单独成行。
- 拆分成多行的参数列表后，结束括号以及最后一个参数**必须**写在同一行，其开始花括号可以写在同一行，也可以独占一行；结束花括号**必须**在方法主体后单独成一行。

```php
<?php 

namespace VendorgiPackage;  

class ClassName 
{

    public function aVeryLongMethodName(
        ClassTypeHint $arg1,
        &$arg2,
        array $arg3 = []) {
        // method body     
    }
}

```

### 7.7 `abstract` 、 `final` 、 以及 `static`
- 需要添加 `abstract` 或 `final` 声明时， **必须**写在访问修饰符前，而 `static` 则**必须**写在其后。

```php
<?php

namespace VendorgiPackage;

abstract class ClassName 
{
    protected static $foo;

    abstract protected function zim();

    final public static function bar()
    {
        // method body     
    }
}

```

### 7.8 方法及方法调用
- 方法及方法调用时，方法名与参数左括号之间一定不能有空格，参数右括号前也一定不能有空格。每个参数前一定不能有空格，但其后必须有一个空格。

```php
<?php

bar(); 
$foo->bar($arg1); 
Foo::bar($arg2, $arg3);

```

- 参数列表超过140个字符可以分列成多行，此时包括第一个参数在内的每个参数都**必须**单独成行。

```php
<?php

$foo->bar(
    $longArgument,
    $longerArgument,
    $muchLongerArgument);

```

## 8. 控制结构 `control structures`
控制结构的基本规范如下：

- 控制结构关键词后**必须**有一个空格。
- 左括号 `(` 后一定不能有空格。
- 右括号 `)` 前也一定不能有空格。
- 右括号 `)` 与开始花括号 `{` 间一定有一个空格。
- 结构体主体一定要有一次缩进。
- 结束花括号 `}` 一定在结构体主体后单独成行。
- 每个结构体的主体都**必须**被包含在成对的花括号之中， 这能让结构体更加标准，以及减少加入新行时，引入出错的可能性。

### 8.1 `if` 、 `elseif` 和 `else`
- 标准的 `if` 结构如下代码所示，留意 括号、空格以及花括号的位置， 注意 `else` 和 `elseif` 都与前面的结束花括号在同一行。

```php
<?php 

if ($expr1) {
    // if body 
} elseif ($expr2) {
    // elseif body 
} else {
    // else body; 
}

// 单个if也必须带有花括号
if ($expr1) {
    // if body
}

```

应该使用关键词 `elseif` 代替所有 `else if` ，以使得所有的控制关键字都像是单独的一个词。

### 8.2 `switch` 和 `case`
标准的 `switch` 结构如下代码所示，留意括号、空格以及花括号的位置。 `case` 语句必须相对 `switch` 进行一次缩进，而 `break` 语句以及 `case` 内的其它语句都 必须 相对 `case` 进行一次缩进。 如果存在非空的 `case` 直穿语句，主体里必须有类似 `// no break` 的注释。

```php
<?php 

switch ($expr) {
    case 0:
        echo 'First case, with a break';
        break;

    case 1:
        echo 'Second case, which falls through';
        // no break

    case 2:
    case 3:
    case 4:
        echo 'Third case, return instead of break';
        return;

    default:
        echo 'Default case';
        break;
}

```

### 8.3 `while` 和 `do while`
一个规范的 `while` 语句应该如下所示，注意其 括号、空格以及花括号的位置。

```php
<?php

while ($expr) {
    // structure body 
}

```

标准的 `do while` 语句如下所示，同样的，注意其 括号、空格以及花括号的位置。

```php
<?php 

do {
    // structure body; 
} while ($expr);

```

### 8.4 `for`
标准的 `for` 语句如下所示，注意其 括号、空格以及花括号的位置。

```php
<?php 

for ($i = 0; $i < 10; $i++) {
    // for body 
}

```

### 8.5 `foreach`
标准的 `foreach` 语句如下所示，注意其 括号、空格以及花括号的位置。

```php
<?php 

foreach ($iterable as $key => $value) {
    // foreach body 
}

```

### 8.6 `try`, `catch`
标准的 `try catch` 语句如下所示，注意其 括号、空格以及花括号的位置。

```php
<?php 

try {
     // try body 
} catch (FirstExceptionType $e) {
    // catch body 
} catch (OtherExceptionType $e) {
    // catch body 
}

```

## 9 闭包
- 闭包声明时，关键词 `function` 后以及关键词 `use` 的前后都**必须**要有一个空格。
- 开始花括号**必须**写在声明的下一行，结束花括号**必须**紧跟主体结束的下一行。
- 参数列表和变量列表的左括号后以及右括号前，**必须**不能有空格。
- 参数和变量列表中，逗号前**必须**不能有空格，而逗号后**必须**要有空格。
- 闭包中有默认值的参数**必须**放到列表的后面。
- 标准的闭包声明语句如下所示，注意其 括号、逗号、空格以及花括号的位置。

```php
<?php

$closureWithArgs = function ($arg1, $arg2)
{
    // body 
};

$closureWithArgsAndVars = function ($arg1, $arg2) use ($var1, $var2)
{
    // body 
};

```

- 参数列表以及变量列表可以分成多行，这样，包括第一个在内的每个参数或变量都**必须**单独成行。

以下几个例子，包含了参数和变量列表被分成多行的多情况。

```php
<?php

$longArgsNoVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument) {
    // body
};

$noArgsLongVars = function () use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3) {
    // body
};

$longArgsLongVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument) use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3) {
    // body
};

$longArgsShortVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument) use ($var1) {
    // body
};

$shortArgsLongVars = function ($arg) use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3) {
    // body
};

```

注意，闭包被直接用作函数或方法调用的参数时，以上规则仍然适用。

```php
<?php

$foo->bar(
    $arg1,
    function ($arg2) use ($var1) {
        // body
    }, $arg3);

```

## 10  注释
### 10.1 文件注释
- 注释开始应该使用 `/*`， 不可以使用 `/**`；结束应该使用 `*/`； 不可以使用 `**/`。
- 第二行php版本信息，版本信息后一空行。
- 注解内容对齐，注解之间不可有空行。
- 星号和注释内容中间必须是一个空格。
- 保持注解顺序一致 `@copyright` 然后　`@link`　再　`@license`。

```php
/*
 * PHP version 5.5
 *
 * @copyright Copyright (c) 2005-2015 XXXX. (http://www.xxx.com)
 * @link       http://www.xxx.com
 * @license    xxx公司版权所有
 */

namespace VendorgiPackage;

class ClassName
{
    public function aVeryLongMethodName(
        ClassTypeHint $arg1,
        &$arg2,
        array $arg3 = []) {
        // method body
    }
}

```

### 10.2 类注释
- 注释开始应该使用 `/**`, 不可以使用 `/*`;结束应该使用 `*/`， 不可以使用 `**/`。
- 第二行开始描述，描述后一空行。
- 注解内容对齐，注解之间不可有空行。
- 星号和注释内容中间必须是一个空格。
- 保持注解顺序一致 `@author` 然后 `@since` 再 `@version`。

```php
<?php

namespace VendorgiPackage;

/**
 * 我是类描述信息哦！
 *
 * @author  Author
 * @since   2015年1月12日
 * @version 1.0
 */
class ClassName
{
    public function aVeryLongMethodName(
        ClassTypeHint $arg1,
        &$arg2,
        array $arg3 = []) {
        // method body
    }
}


```

### 10.3 属性注释
- 注释开始应该使用 `/**`， 不可以使用 `/*`，结束应该使用 `*/`， 不可以使用 `**/`。
- 星号和注释内容中间必须是一个空格。
- 使用 `var` 注解并注明类型。
- 注解基本类型包括 `int`、`sting`、`array`、`boolea`、具体类名称。

```php
<?php

class Cache
{
    /**
     * 缓存的键值
     * @var string
     */
    public static $cacheKey = '';

    /**
     * 缓存的键值
     * @var string
     */
    public static $cacheTime = 60;

    /**
     * 缓存的对象
     * @var \CacheServer
     */
    public static $cacheObj = null;
}
```

### 10.4 方法注释
- 注释开始应该使用 `/**`， 不可以使用 `/*`；结束应该使用 `*/`， 不可以使用 `**/`。
- 第二行开始方法描述，方法描述后一空行。
- 注解内容对齐，注解之间不可有空行。
- 星号和注释内容中间必须是一个空格。
- 注解顺序为 `@param`，`@return`，`@author` 和 `@since`，参数的顺序必须与方法参数顺序一致。
- 参数和返回值注解包括基本类型（`int/sting/array/boolean/unknown`）和对象，如果多个可能类型使用 `|` 分割。
- 如果参数类型为对像必须指定参数类型为具体的类名，如下的 `$arg1` 参数。
- 如果参数类型为 `array` 必须指定参数类型为 `array` 。如下 `$arg2`。
- 需要作者和日期注解，日期为最后修改日期。

```php
/**
 * 我是方法描述信息
 *
 * @param ClassName $arg1 参数1描述　我是具体的对象类型哦
 * @param array $arg2 参数2描述　我是数据类型哦
 * @param int $arg3 参数3描述  我是基本数据类型哦
 * @return boolean
 * @author Author
 * @since  2015年1月12日
 */  
public function methodName(ClassName $arg1, array $arg2, $arg3) 
{     
    // method body
    return true;
}

```

### 10.5 其他注释
- 代码注释尽量使用 `//`
- 注释内容开始前必须一个空格
- 代码行尾注释 `//` 前面必须一个空格
- 代码注释与下面的代码对齐

```php
<?php

class ClassName
{

    public function methodName(ClassName $arg1, array $arg2, $arg3)
    {
        // 这里是注释哦 注释内容前是有一个空格的哦
        for ($i = 0; $i < 10; $i++) {
            // for body 注释和下面的代码是对齐的哦
            $a++; // 代码行尾注释‘//’前面必须一个空格
        }

        return true;
    }

}
```

- 多行注释时使用 `/* *  ......*/`

```php
class ClassName
{
    public function methodName(ClassName $arg1, array $arg2, $arg3)
    {
        /**
         *  这是多行注释哦
         *　这是多行注释哦
         */
        for ($i = 0; $i < 10; $i++) {
            // for body
        }
    }

}
```