---
title: laravel框架对__call魔术方法的使用
date: 2019-12-04 16:10
tag: laravel
categories:
- PHP
- Laravel
---

# Index
 - [Macroable](#Macroable)
    - [macro](#macro)
    - [mixin](#mixin)
    - [原理](#原理)
 - [Manager](#Manager)
 - [increment/decrement](#increment/decrement)

# Macroable
Macroable是一个在laravel框架中运用很广泛的trait，被称作宏，其主要作用是给一个既有的对象，动态的添加新的方法。Macroable有两种用法给对象添加方法，分别是`macro`和`mixin`。

## macro
macro方法用于给对象动态的添加单个方法。该方法有两个参数，第一个参数是被添加的`方法名`，第二个参数是一个可调用结构：匿名函数、函数名、对象方法的数组、实现了__invoke方法的对象等。
```php
class Target{
    use Illuminate\Support\Traits\Macroable;
}

Target::macro('sum', 'array_sum');

(new Target)->sum([1,2,3, 4]); // 10
```

## mixin
mixin方法用于给对象动态的添加多个方法。该方法会将参数对象所有的`public/protected`方法，动态的添加给目标对象。e.g:

```php
class Target{
    use Illuminate\Support\Traits\Macroable;
}

class Base{
    public function name(){
        return function(){
            echo __CLASS__;
        };
    }
}

Target::mixin(new Base);

(new Target)->name(); // Base
```
如果你了解关于javascript的原型继承的话，就会发现Macroable的mixin方法，简直和js的原型继承一模一样。这也算是php除extends语法外的另一种继承的实现吧。

## 原理
Macroable的实现，是利用了php的魔术方法`__call/__callStatic`，新增方法时，将方法名与实现保存在一个数组中，然后在调用时通过魔术方法，查找被调用方法是否有macro记录，有则执行调用。

# Manager
Manager是laravel中驱动管理的抽象类。Manager抽象类运用了建造者模式。像框架中的Session、Notifications、Mail等功能模块都是直接继承自该类，其他如认证、缓存、数据库、队列等模块都用到了这一思想。

驱动对象通过形如`createXxxDriver`的方法进行创建，建造出名为`xxx`的驱动对象。每定义一个`createXxxDriver`方法，就相当于定义了一个驱动。如果在运行时发现需要新增驱动怎么办？也可以通过`extend`方法来扩展驱动。

除了驱动定义的方法外，如果调用Manager对象的其他方法，都会通过魔术方法`__call`转发到对应驱动上进行调用。

```php
class IMManger extends \Illuminate\Support\Manager {
    public function createRtxDriver(){
        return new RTX;
    }

    public function getDefaultDriver(){
        return 'rtx';
    }
}

$manager = new IMManger;

// 1. 指定消息驱动发送消息
$manager->driver('rtx')->send();

// 2. 扩展消息驱动
$manager->extend('yx', function(){
    return new YX;
})
$manger->driver('yx')->send();

// 3. 使用默认驱动发送消息
$manager->send();
```


# increment/decrement
如果看过laravel文档，应该都知道Eloquent模型上`increment/decrement`这一对方法，用来对模型的字段做自增与自减的操作，其实这对方法有两种用法：

```php
// 用法一：对单条数据操作
$user = User::find(1);
$user->increment('age');

// 用法二：对全表数据操作
User::increment('age');
```

为何increment方法，既可以在类实例上进行调用，同时又能通过类名进行静态调用呢？毕竟一个类中是无法定义两个同名方法的。原来Model类中虽然定义了`increment/decrement`两个方法，但他们的访问控制都是声明为`protected`的，无论是实例调用还是静态调用，都会转发到魔术方法`__call/__callStatic`上面，而后经过一系列的操作，调用`increment/decrement`，再此期间，Model类会检查模型是否“装载过数据”，有则需要将数据作为限制条件，更新指定数据，否则更新全表的数据。

```php

namespace Illuminate\Database\Eloquent;
abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable, QueueableEntity, UrlRoutable
{

    // 注意此处访问控制声明为protected
    protected function increment($column, $amount = 1, array $extra = [])
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
    }

    protected function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        $query = $this->newQuery();

        // 这里检查当前模型对象是否“装载过数据”
        if (! $this->exists) {
            return $query->{$method}($column, $amount, $extra);
        }

        $this->incrementOrDecrementAttributeValue($column, $amount, $extra, $method);

        // 装载过数据的模型对象，会把主键数据作为限制条件传入
        return $query->where(
            $this->getKeyName(), $this->getKey()
        )->{$method}($column, $amount, $extra);
    }


    public function __call($method, $parameters)
    {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        return $this->newQuery()->$method(...$parameters);
    }


    public static function __callStatic($method, $parameters)
    {
        // 注意此处new了一个空的模型对象，
        return (new static)->$method(...$parameters);
    }
}
```