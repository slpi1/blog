---
title: 错误与异常的处理
date: 2019-11-01 17:15
tags:
- PHP
---

# Index
 - [概念](#概念)
    - [什么是异常](#什么是异常)
        - [异常的捕获](#异常的捕获)
        - [异常未捕获的情况](#异常未捕获的情况)
    - [什么是错误](#什么是错误)
        - [错误级别](#错误级别)
        - [错误的捕获](#错误的捕获)
        - [错误未捕获的情况](#错误未捕获的情况)
 - [模拟错误的产生](#模拟错误的产生)
    - [E_NOTICE](#E_NOTICE)
    - [E_WARNING](#E_WARNING)
    - [E_ERROR](#E_ERROR)
 - [异常的运用](#异常的运用)
    - [前提](#前提)
    - [使用](#使用)

# 概念
首先要注意区分错误与异常的概念。

## 什么是异常
所有的异常都由一个基类：Exception。异常是指在代码中由程序猿通过 `throw new Exception` 语法主动抛出的。

### 异常的捕获
异常可以通过两种方式进行捕获：
- `try/catch` 语句块进行捕获
- `set_exception_handler` 异常处理函数

> Exception 异常可以被第一个匹配的 try / catch 块所捕获。如果没有匹配的 catch 块，则调用异常处理函数（事先通过 set_exception_handler() 注册）进行处理。

### 异常未捕获的情况
如果没有对异常进行捕获，就会产生一个致命错误。
> 如果尚未注册异常处理函数，则按照传统方式处理：被报告为一个致命错误（Fatal Error）。


## 什么是错误
错误是代码在运行过程中产生的，一般不由程序猿主动抛出。当出现错误时，说明代码中有bug，需要进行修复。

### 错误级别
错误有级别之分。经常遇到的错误级别有下列几种:

| 值 | 常量| 说明 |
|---|---|---|
| 1 | E_ERROR |致命的运行时错误。这类错误一般是不可恢复的情况，例如内存分配导致的问题。后果是导致脚本终止不再继续运行。|
| 2 | E_WARNING |运行时警告 (非致命错误)。仅给出提示信息，但是脚本不会终止运行。|
| 8 | E_NOTICE |运行时通知。表示脚本遇到可能会表现为错误的情况，但是在可以正常运行的脚本里面也可能会有类似的通知。|

其他的错误类型在日常开发中可能遇到的不是很多，就不进行一一列举。其中, `E_ERROR` 类型的错误分为可捕获错误与致命错误。

### 错误的捕获
错误也是可进行捕获的。在PHP7中，由于改变了大多数错误的报告方式，大多数错误被作为 Error 异常抛出，也可以通过  try / catch 块进行捕获。
- `try/catch` 语句块进行捕获
- `set_exception_handler` 异常处理函数
- `set_error_handler` 错误处理函数
- `register_shutdown_function => error_get_last`

set_error_handler 错误处理函数所能捕获的错误有限。
> 以下级别的错误不能由用户定义的函数来处理： E_ERROR、 E_PARSE、 E_CORE_ERROR、 E_CORE_WARNING、 E_COMPILE_ERROR、 E_COMPILE_WARNING，和在 调用 set_error_handler() 函数所在文件中产生的大多数 E_STRICT。

register_shutdown_function 会由以下情况触发：
 - 脚本正常退出时
 - 在脚本运行(run-time not parse-time)出错退出时
 - 用户调用exit方法退出时

也就是说 register_shutdown_function 被执行时，并不能捕获到错误，需要在函数体内，由error_get_last来捕获最后产生的错误。

按错误级别由低到高来分的话，上述捕获手段可以分别捕获到如下表所示的错误：

| 错误级别 | 备注 | 捕获手段 |
|---|---|---|
| 8/E_NOTICE | | set_error_handler/register_shutdown_function |
| 2/E_WARNING | | set_error_handler/register_shutdown_function |
| 1/E_ERROR | 可捕获错误 | try/set_exception_handler/register_shutdown_function |
| 1/E_ERROR | 致命错误 | register_shutdown_function |

### 错误未捕获的情况
程序bug。

综上所述，如果在抛出一个异常之后：
 - 没有 try / catch , set_exception_handler 进行捕获，会报告为一个错误
 - 没有 set_error_handler 进行错误的捕获（其实 set_error_handler 也无法捕获上述错误）
 - 没有 register_shutdown_function、error_get_last 进行捕获
则程序会中断运行。

# 模拟错误的产生

错误的模拟主要是为了方便验证上述捕获手段，并不会在实际中进行运用，关于错误模拟可以参考文件夹[error](./error)部分的代码

## E_NOTICE

```php
//$test未定义，会报一个notice级别的错误
return $a;
```

## E_WARNING

```php
$array = [1];

// in_array函数需要传入两个参数，会报一个warning级别的错误
in_array($array);
```

## E_ERROR

可捕获错误：
```php
function sum(Array $array){

}
// sum指定传入一个数组参数，会报一个TypeError的可捕获错误
sum('a');
```

致命错误:
```php

// 如果这里test的定义不放在if中，会在编译阶段报告语法错误，而不会进入到运行时。
if (true) {
    function test()
    {}
}
function test()
{}
```

# 异常的运用

## 前提
在运用异常之前，务必先了解框架的异常处理机制，或者自行设计异常的处理机制。laravel框架的异常处理逻辑主要在 `Illuminate\Foundation\Bootstrap\HandleExceptions::class` 这个类中。

## 使用

异常的使用，是指通过异常捕获机制，根据捕获到的不同类型的异常，来决定作出什么样的处理。我在项目中比较常用的技巧，是通过异常来决定接口的响应，在异常捕获的逻辑中有以下两个逻辑分支:

```php
if ($exception instanceof ErrorLogicException) {
    // 逻辑错误，需要反馈给用户阅读，并翻译为用户当前语系
    return response()->json([
        'code' => $exception->getCode(),
        'data' => '',
        'msg'  => $exception->getMessage(),
    ]);
} elseif ($exception instanceof ErrorDebugException) {
    // debug 错误，给用户返回一个统一的信息
    // 日志记录错误信息，debug模式下直接返回错误信息，用于调试
    $info = $exception->getMessage();
    Log::debug($info);
    return response()->json([
        'code' => $exception->getCode(),
        'data' => '',
        'msg'  => config('app.debug') ? $info : __('common.failed'),
    ]);
}
```

先定义两种异常类型:
 - ErrorLogicException: 流程逻辑错误。指没有按照规定使用，产生的异常类型。这个时候会返回明确的错误提示，或者指导用户什么是正确的操作。
 - ErrorDebugException: 非正常错误，泛指不可预知的错误，但是会影响流程的正确性。这个时候并不需要告知用户发生了什么错误，但是需要记录错误发生的相关信息，交由开发者进一步分析问题产生的原因。比如：数据保存失败等小概率事件。

然后在需要的地方抛出对应的异常即可。

对于ErrorLogicException异常的情形，并非一定要按上述逻辑来处理，因为在抛出ErrorLogicException异常的情形下，都是确定出错了的情况，也可以通过 `return false` 来返回函数的调用栈，如果代码的层级比较深，可能要经过`Model -> Service -> Controller` 或更多的层级来return到Controller，这个时候用异常就会有“短路”的效果，避免过多层级的return。