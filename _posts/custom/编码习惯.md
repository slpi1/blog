---
title: 编码习惯
date: 2019-10-25 16:07
---

 - 单引号与双引号的使用：纯字符串使用单引号包括，而不是使用双引号。
 - 字符串与变量的拼接：
    - 通过点号(.)连接字符串与变量。
    - 双引号的写法，变量使用中括号包括。
``` php
$str = "Hello World. I'm {$name}.";
```
 - 同一个方法体内不要用相同的变量表示不同的含义。
 - foreach循环数组时，as后面避免使用引用。
```php
foreach ($array as $key => & $value) {

}
```
 - 编码过程中进行逻辑运算时，尽量避免出现无意义的罗马数值，通过使用常量来代替。此外，其他地方为了表意明确，都推荐做如上处理。
```php
 //错误示范
 if ($user->type == 1) {
    $isAdmin = true;
 }

 // 正确示范
 Class User{
    const TYPE_ADMIN = 1;
 }
 if ($user->type == User::TYPE_ADMIN) {
    $isAdmin = true;
 }
```
 - 对类型明确的函数参数，进行类型声明。
```php
//错误示范
function getUserName($user){
    return $user->name;
}

// 正确示范
function getUserName(User $user){
    return $user->name;
}

 ```