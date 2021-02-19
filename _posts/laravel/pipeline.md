---
title: Laravel Pipeline源码分析
date:  2020-01-03 15:38
---

# Index
 - [Pipeline的使用](#Pipeline的使用)
    - [send](#send)
    - [through](#through)
    - [then](#then)
 - [Pipeline的实现](#Pipeline的实现)
    - [管道的设计0](#管道的设计0)
    - [管道的设计1](#管道的设计1)
    - [管道的设计10](#管道的设计10)

> `Pipeline` 模型最早被使用在 `Unix` 操作系统中。管道的出现，所要解决的问题，还是软件设计中的设计目标——高内聚，低耦合。它以一种“链式模型”来串接不同的程序或者不同的组件，让它们组成一条直线的工作流。**这样给定一个完整的输入，经过各个组件的先后协同处理，得到唯一的最终输出。**

# Pipeline的使用

```php
use Illuminate\Pipeline\Pipeline;
$result = (new Pipeline($container))
    ->send($passable)
    ->through($pipes)
    ->then($callable);
```

## send
 - 参数: `$passable`

**给定一个完整的输入**就是send所要做的事情。在使用管道模式之前，首先得想清楚，我们是要通过管道来处理什么东西。拿 `Laravel` 应用来举例，`web` 程序要通过**中间件**来处理**请求**，最终得到**响应**返回给浏览器。那么，请求就是这里给定的输入。

## through
 - 参数: `$pipes`

**经过各个组件的先后协同处理**中提及的各个组件，就是由 `through` 方法来传入。基于管道模式的特点，这里的各个组件，会有相同类型的输入，相同类型的输出以及相同的执行入口。

```php
public function handle($request, Closure $next)
{
    // do something
    $response = $next($request);
    // do something
    return $response;
}
```

## then
 - 参数: `$callable`

**最终输出**就是 `then` 要做的事。前面 `send` 以及 `through` 都只是定义管道执行过程中所需要的参数，真正的执行过程在 `then` 这个方法中。`then` 的功能点在于将输入转化为输出。

# Pipeline的实现

## 管道的设计0
先考虑一种最容易想到的管道的实现方式，我们假设如下：

```php
use Input;
use Output;

$input = new Input;

// 通过管道
$result = pipeOne($input);
$result = pipeTwo($result);
$result = pipeThree($result);

// 转化输出
(Output) $output = then($result);
```

结合我们上文提到的`各个组件，会有相同类型的输入，相同类型的输出以及相同的执行入口`,如果通过上述方式来实现管道，那么实际上管道中的组件`pipeOne/pipeTwo/pipeThree`的输入与输出都是同一种数据类型。否则`$input`通过第一个组件之后，产生的输出就不能作为下一个组件的输入。显然，这种愚蠢的设计方式根本就不能满足需求。

## 管道的设计1
考虑以下面这种方式来实现管道：
```php
use Input;
use Output;

$input = new Input;

function pipeOne(Input $input){
    $input = dosomething($input);
    (Output) $result = pipeTwo($input);
    return $result;

}
function pipeTwo(Input $input){
    $input = dosomething($input);
    (Output) $result = pipeThree($input);
    return $result;

}
function pipeThree(Input $input){
    $input = dosomething($input);
    (Output) $result = howToGetTheResult($input);
    return $result;

}

// 通过管道
$result = pipeOne($input);

// 转化输出
$output = then($result); 
```

通过这种方式来实现管道，仿佛可以满足上文对输入与输出的定义。但显然也存在很大问题，通过这种方式实现的管道，组件执行的顺利被硬编码到了组件的逻辑之中，如果出现了流程变动的问题，要花很大的力气去做修改。其次，最后一个组件怎么来获取 `result` 呢？获取 `result` 的过程，应该定义在 `then` 当中，综上所述，我们要改进的设计，需要解决下面两个问题：
 - 管道中要执行的组件是可配置的。组件的数量与顺序都是可以修改的。
 - 管道要能自行检查到执行的末端，并调用 `then` 方法，将 `input` 转化为 `output`。

## 管道的设计10

```php
use Input;
use Output;

$input = new Input;


function pipeOne(Input $input, Callable $callback){
    // dosomething
    (Output) $result = $callback($input, $callback);
    return $result;
}

function pipeTwo(Input $input, Callable $callback){
    // dosomething
    (Output) $result = $callback($input, $callback);
    return $result;
}

function pipeThree(Input $input, Callable $callback){
    // dosomething
    (Output) $result = $callback($input, $callback);
    return $result;
}


function createCallbackOfPipe($pipes, $index){

    return function($input, $callback) use($pipes,$index){
        // 自动检测管道的末端
        if($index == count($pipes)){
            return then($input);
        }else{
            $index+1;
            $nextPipe = $pipes[$index];    
        
            return $nextPipe($input,createCallbackOfPipe($pipes, $index));
        }
    }
}

// 这里可以定义管道中的组件顺序及数量
$pipes = [
    'pipeOne',
    'pipeTwo',
    'pipeThree',
];
$firstPipe = $pipes[0];
(Output) $result = $firstPipe($input, createCallbackOfPipe($pipes, 0));

```

至此，一个管道的模型就基本实现了。我们重新梳理一下管道设计中要注意的细节问题，可以归纳出以下几点：
 - 管道中的每一个组件，都有相同类型的输入与输出。
 - 管道的参数中还要传递**下一次要调用的句柄**，组件除了要执行本身的逻辑外，还需要调用这个句柄，来触发下一个组件的执行。
 - 组件的执行过程，最好封装成一个匿名函数，这样可以变得通用，而不需要知道下一个要执行的组件的具体信息，比如方法名。

在 `Laravel` 框架中，通过一个函数就达到了我们传递**下一次要调用的句柄**的目的，这个函数就是 `array_reduce`,这个方法，简直完美的契合管道的思想啊。此外，`Laravel` 中对管道执行的封装，还考虑到了其他的因素，比如对**下一次要调用的句柄**的扩展，除了可以使用匿名函数，还兼容了 `PHP` 中的其他三种可调用结构，以及对容器的使用等，具体 `Laravel` 是如何实现的，就让大家自行去了解吧。
