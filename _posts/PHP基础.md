---
title: php基础
date: 2015-09-23 14:50
description: php基础知识点。数据类型及转换、数组及相关函数、OOP及高级特性。
keywords: php基础
tags:
- PHP
---

## 数据类型 ##
### 数据类型 ###
#### 标量数据类型 scalar ####
- 布尔值 
- 整形：十进制（decimal），八进制（octal），十六进制（hexadecimal） ，范围大小：2e31
- 浮点型：`double`
- 字符串

#### 复合数据类型 ####
- 数组
- 对象

### 类型强制转换 ###
- `(array)`
- `(bool)`,`(boolean)`
- `(int)`,`(integer)`
- `(object)`
- `(real)`,`(double)`,`(float)`
- `(string)`

### 相关函数 ###
**`gettype(mixed val)` ：八个可能的返回值**
`array`,`boolean`,`double`,`integer`,`object`,`resource`,`string`,`unkwon type`
**`settype(mixed val, string type)`**
`type`可取值为： `array`,`boolean`,`float`,`integer`,`null`,`object`,`string`


### 类型标示符函数 ###
`is_array`,`is_bool`,`is_float`,`is_integet`,`is_null`,`is_numeric`,`is_object`,`is_resource`,`is_scalar`,`is_string`


## 函数 ##
- 按引用传值，在函数定义时为参数加上&:
```
function fn(&$a){
    $a ++;
}
```
- 返回多个值。返回数组，list处理
- 静态变量赋值不能为表达式:
```
    function foo(){
        static $int = 0;          // correct
        static $int = 1+2;        // wrong  (as it is an expression)
        static $int = sqrt(121);  // wrong  (as it is an expression too)
        $int++;
        echo $int;
    }
```


## 数组 ##
`range`函数，`range(0,20,2)` 0到20内所有偶数数组。

### 添加与删除元素 ###
- `int array_unshift(array arr, mixed var[, mixed var])`:在数组头部添加元素，所有数值的键都会相应的修改，以反映其在数组中的新位置，关联键不受影响。
- `int array_push(array arr, mixed val[, mixed var])` :返回压入元素后数组中元素的个数。可以传入多变量。
- `mixed array_shift(array arr)` : 删除并返回第一个元素。数值键键值下移，关联键不受影响。
- `mixed array_pop(array arr)` : 删除并返回数组最后一个元素。

** 添加操作都可以传入多个参数，故被操作数组应作为第一个参数传入。 ** 

### 定位元素 ###
#### 搜索数组 #### 

- `boolean in_array(mixed var, array arr [, boolean strict])`:在数组中搜索值，找到返回true，否则返回false，第三个参数strict表示搜索是考虑类型

- `boolean array_key_exists(mixed var, array arr)`:在数组中寻找特定的键，存在返回true，否则返回false

- `mixed array_search(mixed var, array arr [, boolean strict])`:在数组中搜索值，找到则返回相应的键，否则返回false

** 搜索操作并不会影响原数组，数组参数位在后 ** 

#### 获取数组键 #### 
- `array array_keys(array arr [, mixed search_value])`: 返回一个数组，其中包含所搜索的数组中找到的所有键，如果包含参数`search_value`则只返回与*对应值*匹配的键

#### 获取数组值 #### 

- `array array_value(array arr)`：返回数组中所有的值，并自动提供数值索引。


### 遍历数组 ###
- `key()`:数组指针的键
- `current()`：数组指针的值
- `each()`:数组指针键值对，如果指针位于末尾，返回false。返回格式如下：
```
    array(
        '0'=>'key',
        'key'=>'key',
        '1'=>'value',
        'value'=>'value'
    )
```
- `next()`：数组指针后移一位，返回移动后所指的元素值，或false
- `prev()`：数组指针前移一位
- `reset()`：移到第开始位置，返回第一元素值
- `end()`：移到结束位置，返回最后的元素值

- `boolean array_walk(array &arr, callback fn [, mixed data])`:fn逐个处理数组键值对。当真正需要改变数组键值对时，以引用的方式传递给函数`fn`。
`fn`第一个参数表示当前值，第二个参数表示当前键，存在data时，会当做第三个参数传递给`fn`。


### 数组的大小以及唯一性 ###
#### 确定数组大小 #### 

- `integer count(array arr [, int mode])` 设置model为1时，会进行递归统计，该函数与`sizeof`功能一致

#### 统计元素出现的频度 #### 

- `array array_count_values(array arr)`:返回数组，键名为原元素值，值元素出现的频次


#### 确定唯一的数组元素（去重） #### 

- `array array_unique(array arr)`


### 数组排序 ###
#### 逆序 #### 

- `array array_reverse(array arr [, boolean preserve_keys])` 设置`preserve_keys`保存键值对映射，否则重新分配键，不影响关联键。

#### 置换数组键值对 #### 

- `array array_flip(array arr)`

#### 数组排序 #### 

- `void sort(array arr [, int sort_flags])`：升序排列，直接改变数组，不返回值，会重排键，并且影响关联键。
   `sort_flags` ：
   - `SORT_NUMBERIC`，数值序；
   - `SORT_REGULAR`，`ASCII`序；
   - `SORT_STRING`，自然序。
   
- `void asort()`：升序排列,不影响关联键。

- `void rsort(array arr [,int sort_flags])`:降序排列，影响关联键
- `void arsort(array arr [,int sort_flags])`:降序排列，不影响关联键

- `viod natsort(array arr)` 自然序
- `void natcasesort(array arr)` 不区分大小写自然序

- `void ksort(array arr [, int sort_flags])` 键升序
- `void krsort(array arr [, int sort_flags])` 键降序

- `void usort(array arr, callback fn)` 自定义排序。

#### 排序均会影响原数组，数组参数位第一 #### 


### 合并、拆分、结合、分解数组 ###
#### 合并数组 #### 

- `array array_merge(array arr1 [, array arrN])`:合并两个数组并返回得到的数组。所得到的数组以第一个输入数组参数开始，按后面数组参数出现的顺序依次追加。对于关联键，如果参数数组中包含的某个键已经存在，则覆盖前面的键值对。

> 数组合并还有‘+’运算符，注意与merge的区分。

#### 递归追加数组 #### 

- `array array_merge_recursive(array arr1 [, array arrN])`:合并两个数组并返回得到的数组。所得到的数组以第一个输入数组参数开始，按后面数组参数出现的顺序依次追加。如果参数数组中包含的某个键已经存在，则合并为数组。

#### 链接两个数组 #### 

- `array array_combine(array keys, array value)`：由一组键和值组成。两个数组必须大小相同，不能为空。

#### 拆分数组 #### 

- `array array_slice(array arr, int offset [, int length])`:函数将返回数组的一部分，从键`offset`开始，到`offset+length`的位置结束（不包括）。
不加`length`参数，表示从`offset`直到结束。
`offset`为负表示从倒数位`|offset|`开始，`length`为负表示到倒数位`|length|`为止。

#### 结合数组 #### 

- `array array_splice(array arr, int offset [, int lenght [, array replacement]])` ：函数会删除数组中从`offset`开始到`offset+lenght`结束的所有元素，并以数组的形式返回所删除的元素。
可以使用可选参数`replacement`来替换目标部分。

#### 数组交集 #### 

- `array array_intersect(array arr1, array arr2 [, array arrN])`：函数返回一个保留键的数组，数组只由第一个数组中出现且在其他每个输入数组中都出现的值组成。
元素的值与类型都相同，才能判定为相等。

- `array array_intersect_assoc(array arr1, array arr2 [, array arrN])`：键值对的交集

#### 数组差集 #### 

- `array array_diff(array arr1, array arr2 [, array arrN])`：返回出现在第一个数组中，且其他数组中均没有的值。

- `array array_diff_assoc(array arr1, array arr2 [, array arrN])`：返回出现在第一个数组中，且其他数组中均没有的键值对。


### 其他数组函数 ###
#### 返回一组随机键 #### 

- `mixed array_rand(array arr [, int num])`:忽略`num`参数，只返回一个随机值。设置`num`则返回`num`个元素的随机键数组

#### 随机洗牌数组 #### 

- `void shuffle(array arr)`：对数组随机重排。

#### 值求和 #### 

- `mixed array_sum(array arr)`：返回所有值的和。

#### array_chunk #### 

- `array array_chunk(array arr, int size [, boolean preserve_key])`：将传入数组按`size`均匀的划分。启用`preserve_key`将保持键值对，禁用时重排索引。


###数组函数有下列个函数，要将操作函数置于第二参数位
- `bool in_array ( mixed $needle , array $haystack [, bool $strict ] )`
- `bool array_key_exists ( mixed $key , array $search )`
- `mixed array_search ( mixed $needle , array $haystack [, bool $strict ] )`
- `array array_map ( callback $callback , array $arr1 [, array $... ] )`


## 对象 ##
### OOP的好处 ###
- 封装
- 继承
- 多态

### 基本概念 ###
#### 类 #### 

#### 对象 #### 

#### 字段 #### 

字段作用域
- `public`
- `private`
- `protected`
- `final` -- ** 阻止在子类中覆盖这个字段。 ** 
- `static`

#### 4. 属性 #### 
- 属性设置 `boolean  __set( string $name , mixed $value )`
- 获取属性 `mixed __get ( string $name )`
- 自定义

#### 5. 常量 #### 
- 定义：`const NAME = 'VALUE'`
- 使用：`echo class_name::NAME`

#### 6. 方法 #### 

方法作用域
- `public`
- `private`
- `protected`
- `abstract` -- ** 抽象方法在父类中声明，子类中实现。只有声明为`abstract`的类才能声明抽象方法。 ** 
- `final` -- ** 阻止在子类中覆盖这个方法。 ** 
- `static`

类型提示（类型约束）：函数的参数可以指定只能为对象（在函数原型里面指定类的名字），php 5.1 之后也可以指定只能为数组。 注意，即使使用了类型约束，如果使用NULL作为参数的默认值，那么在调用函数的时候依然可以使用NULL作为实参。类型约束不只是用在类的成员函数里，也能使用在函数里。
```
    //如下面的类
    class MyClass
    {
        /**
         * 测试函数
         * 第一个参数必须为类OtherClass的一个对象
         */
        public function test(OtherClass $otherclass) {
            echo $otherclass->var;
        }


        /**
         * 另一个测试函数
         * 第一个参数必须为数组 
         */
        public function test_array(array $input_array) {
            print_r($input_array);
        }
    }

    //另外一个类
    class OtherClass {
        public $var = 'Hello World';
    }
```
### 构造函数和析构函数 ###
####  构造函数 #### 

- `function __construct(){ ... }`
php不会自动调用父类的构造函数，必须使用`parent`关键字显示调用:`parent::__construct()`。调用其他类的构造函数：`ClassName::__construct()`。

#### 析构函数 #### 

- `function __destruct(){ ... }`

### 静态类成员 ###
声明为`static`，调用方法`self::$var`。由于静态方法不需要通过对象即可调用，所以伪变量`$this`在静态方法中不可用。静态属性不可以由对象通过`->`操作符来访问。

###  instanceof关键字 ###
用以确定一个对象是类的实例、类的子类，还是实现了某个特定的接口，并进行相应的操作。
```
$manage = new Employee();
...
if( $manage instanceof Employee ) echo 'yes';
```

### 辅助函数 ###
####  确定类是否存在 #### 
- `boolean class_exists( string class_name )`

#### 确定对象上下文 #### 
- `string get_class( object obj )` -- 返回对象所属类名。

#### 了解类方法 #### 
- `array get_class_methods( mixed class_name )`

#### 了解类字段 #### 
- `array get_class_vars ( string $class_name )` -- 返回由类的默认属性组成的数组

#### 了解声明类 #### 
- `array get_declared_classes ( void )` -- 返回由已定义类的名字所组成的数组

#### 了解对象字段 #### 
- `array get_object_vars ( object $obj )` --  返回由对象属性组成的关联数组

#### 确定对象的父类 #### 
- `string get_parent_class ([ mixed $obj ] )` -- 返回对象或类的父类名

#### 确定接口是否存在 #### 
- `bool interface_exists ( string $interface_name [, bool $autoload ] )` -- 检查接口是否已被定义

#### 确定对象的子类类型 #### 
- `bool is_subclass_of ( object $object , string $class_name )` -- 如果此对象是该类的子类，则返回 TRUE。自 PHP 5.0.3 起也可以用一个字符串来指定 `object` 参数（类名）。

#### 确定方法是否存在 #### 
- `bool method_exists ( object $object , string $method_name )` -- 检查类的方法是否存在

## 高级OOP特性 ##
### 克隆 ###
#### 使用 #### 
`destinationObject = clone targetObject`

#### __clone()方法 #### 
用以调整对象的克隆行为。会在克隆操作期间执行。

### 继承 ###
类的继承通过关键字`extends`实现。
`class Child extends Parent { ... }`

### 接口（interface） ###
#### 定义: #### 
我们可以通过`interface`来定义一个接口，就像定义一个标准的类一样，但其中定义所有的方法都是空的。 
你可以指定某个类必须实现哪些方法，但不需要定义这些方法的具体内容。 接口中定义的所有方法都必须是`public`，这是接口的特性。 
```
    interface iTemplate
    {
        public function setVariable($name, $var);
        public function getHtml($template);
    }
```
#### 实现: #### 
要实现一个接口，可以使用`implements`操作符。类中必须实现接口中定义的所有方法，否则 会报一个fatal错误。如果要实现多个接口，可以用逗号来分隔多个接口的名称。 
```
    class Template implements iTemplate
    {
        private $vars = array();

        public function setVariable($name, $var)
        {
            $this->vars[$name] = $var;
        }

        public function getHtml($template)
        {
            foreach($this->vars as $name => $value) {
                $template = str_replace('{' . $name . '}', $value, $template);
            }

            return $template;
        }
    }
```
#### 常量: #### 
接口中也可以定义常量。接口常量和类常量的使用完全相同。 它们都是定值，不能被子类或子接口修改。 
```
    interface a
    {
        const b = 'Interface constant';
    }
```
### 抽象类 ###
抽象类不能直接被实例化，你必须先继承该抽象类，然后再实例化子类。~~抽象类中 ** 至少 ** 要包含一个抽象方法~~（部分网络翻译版php手册在此处有误，原文：any class that contains at least one abstract method must also be abstract. 如果类包含了抽象方法，就必须被声明为抽象类）。如果类方法被声明为抽象的，那么其中就不能包括具体的功能实现。

继承一个抽象类的时候，子类必须实现抽象类中的所有抽象方法；另外，这些方法的可见性 必须和抽象类中一样（或者更为宽松）。如果抽象类中某个抽象方法被声明为`protected`，那么子类中实现的方法就应该声明为`protected`或者`public`，而不能定义为`private`。

### 命名空间 ###