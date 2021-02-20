---
title: php 中的引用计数、写时复制、写时改变
date: 2016-05-19 10:21
keywords: php基础
tags:
- PHP
---

php 中的这三个概念涉及到php 变量的实现，[zval结构](http://www.laruence.com/2008/08/22/412.html):
```
typedef struct _zval_struct {
    zvalue_value value;  // 保存变量的值
    zend_uint refcount;  // 变量引用数
    zend_uchar type;     // 变量类型
    zend_uchar is_ref;   // 是否引用
} zval;
```
其中，`refcount、is_ref`是问题的主角。

## 引用计数 ##
- 例一
```
<?php
   $var = 1;
   $var_dup = $var;
?>
```
在上述代码中，第一行创建一个变量$var，并为其赋值1。实际上是创建了一个zval 的数据结构，并将变量$var 与之关联，此时zval 的 refcount=1。第二行将变量$var 赋值给新的变量$var_dump，在这一步中，$var_dump实际上也是与前文中的zval 数据结构进行关联，refcount加一。引用计数在赋值的时候发生。这样处理的结果是可以节省内存开销。
<!-- more -->
## 写时复制 ##
- 例二
```
<?php
    $var = 1;
    $var_dup = $var;
    $var = 2;
?>
```

上述代码的前两行与例一一致，在第三行修改$var 的值时，PHP 检查发现zval的refcount>1，于是复制一份zval 出来与变量$var 关联（变量$var 与变量$var_dup 进行分离），并修改其值为2。同时，原zval的refcount减一。

## 写时改变 ##
- 例三
```
<?php
    $var = 1;
    $var_ref = &$var;
    $var_ref = 2;
?>
```
上述代码第二行进行了引用赋值，与普通赋值过程不同的是，该过程除了增加zval 的引用计数refcount 之外，还会将zval 的is_ref设为1。然后第三行修改变量$var_ref 的值的时候，PHP检查到，虽然zval的refcount大于1，但是is_ref值也为一，所以不执行分离，直接修改zval的值。

- 例四
```
<?php
    $var = 1;
    $var_dup = $var;
    $var_ref = &$var;
?>
```
上述代码第一行先创建一个zval，与变量$var关联。第二行将变量$var赋值给$var_dup，zval的refcount加一。第三行将变量$var 引用赋值给变量$var_ref，在zval的is_ref被设为1之前，检查到refcount大于1，于是PHP会先将变量$var_dup（或者是$var?）分离出去，再将变量$var 的is_ref设为1，refcount加一。



