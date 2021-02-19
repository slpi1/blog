---
title: laravel Auth源码分析
date: 2019-07-01 09:30
---

## Index
 - [Auth模块](#Auth模块)
    - [AuthManager](#AuthManager)
    - [Guard](#Guard)
        - [SessionGuard与TokenGuard](#SessionGuard与TokenGuard)
    - [UserProvider](#UserProvider)
 - [Auth与框架的关系](#Auth与框架的关系)
    - [AuthServiceProvider](#AuthServiceProvider)
    - [路由解析](#路由解析)
    - [路由中间件](#路由中间件)

`Auth` 模块用于处理用户认证。在源码中，关于 `Auth` 模块，有两处命名空间：
 - `Illuminate\Auth`: `Auth` 模块核心代码。这部分的代码都是关于 `Auth` 模块的实现原理及逻辑。
 - `Illuminate\Foundation\Auth`: `Auth` 模块应用功能。这部分是 `Auth` 模块在应用层的一些功能的实现。

## Auth模块
三大组成部分
 - `AuthManager`: 认证管理器
 - `Guard`: 认证器 `or` 看守器
 - `UserProvider`: 用户提供者

### AuthManager
`AuthManager` 是用户认证模块功能的入口，是 `Auth` 类指代的实例。 `AuthManager` 的职责在于管理及扩展 `Guard` 与 `UserProvider`，这是他的“本职工作”，如果在使用过程中，我们不涉及对认证功能的扩展，一般不会用到这部分；`AuthManager` 的另一个职责，在于充当模块功能的入口，转发应用中对于 `Auth` 类的调用到 `Guard`，比如：

```
Auth::user();
Auth::check();
Auth::login($user);

// 等同于
Auth::guard('web')->user();
Auth::guard('web')->check();
Auth::guard('web')->login($user);
```

### Guard
`Guard` 用于实现认证功能，在 `AuthManager` 实例化 `Guard` 时，会绑定一个 `UserProvider` 给 `Guard` ，用于后续提供用户实例。框架实现了 `SessionGuard` 及 `TokenGuard` ，分别用于使用 `session`，`token` 做用户认证的场景。`Guard` 的认证逻辑可以概括为：`Guard` 从上下文中获取登陆凭证，将登陆凭证传递给 `UserProvider`，查询出登陆用户的实例返回给 `Guard`。

#### SessionGuard与TokenGuard
`Session` 是非常常用的认证手段，框架实现的 `SessionGuard` 除了拥有认证功能外，还赋予了登陆与退出的功能。这里简单描述一下认证，登陆与退出的概念：
 - 登陆：客户端提交认证资料，经服务端验证成功后，生成登陆凭证，保存到相应位置，完成登陆。
 - 认证：服务端检查登陆凭证是否存在及有效，有则完成认证，请求放行。
 - 退出：服务端销毁登陆凭证，完成退出。

只有认证才是 `Guard` 的职责，其他两个并不是 `Guard` 的职责。基于不同的认证实现，登陆与退出功能可能会交给其他模块完成。比如基于 `JWT` 的 `Token` 认证方式，其登陆凭证是保存在客户端的，服务端不保存，所以服务端无法主动销毁登陆凭证，也就没有退出功能。然而基于 `Session` 的认证方式，登陆凭证是保存在服务端的，所以基于 `Session` 认证的方式，**可以**提供退出功能。

`SessionGuard` 实现的登陆功能，也就是文档中所指的“手动认证用户”部分。`SessionGuard` 提供 `attempt` 接口，用于用户登陆，同时提供了 `logout` 接口，实现了退出功能。`TokenGuard` 并没有这两个功能。

### UserProvider
用户提供者接收由Guard传递的用户标识，查询出用户实例并返回。框架实现了 `EloquentUserProvider` 与 `DatabaseUserProvider` ，分别需要在 `Auth` 配置中指定用户模型与用户表。大多数情况都是使用 `EloquentUserProvider`。

## Auth与框架的关系
要完整的了解 `Auth` 模块的认证过程，需要结合框架的其他模块及细节来解读。

### AuthServiceProvider
和其他模块一样，`Auth` 模块也是由服务提供者注册，在 `Laravel` 应用生命周期中，处于第二阶段（容器启动）的结束阶段，在这里第一次与 `Request` 产生互动：

```php
// Illuminate\Auth\AuthServiceProvider

protected function registerRequestRebindHandler()
{
    $this->app->rebinding('request', function ($app, $request) {
        $request->setUserResolver(function ($guard = null) use ($app) {
            return call_user_func($app['auth']->userResolver(), $guard);
        });
    });
}
```
`Auth`服务注册时，给 `request` 绑定了一个“重绑定”事件，该事件的目的何在？

首先需要知道，`request` 对象的实例化，是在容器启动之前，是一个比较早的阶段，可以说，在 `request` 对象第一次被实例化时，容器中基本还没有其他对象的存在，那么，如果在代码后续执行的过程中，需要丰富 `request` 对象，该怎么办呢？答案就是重绑定，在合适的时机，更新 `request` 对象之后，重新将 `request` 对象绑定到容器中。

`Auth` 服务注册时，给 `request` 对象重绑定了一个事件，用于给 `request` 添加“用户解析”功能，当使用 `request` 的“用户解析”功能时，实际上会去找 `Guard` 要用户。然而，在服务注册阶段，`Guard` 表示我也还没实例化，你不能立刻来找我要用户，而是要“推迟”找我要用户的时间，所以，最终在这里绑定的是一个闭包，保存的是 `request` 解析用户的途径，在合适的时机，通过这一途径，即可找 `Guard` 要到用户，但这时机究竟是什么时候呢？这个时机，必须满足两个条件：
 - `request` 发生了重绑定
 - `Guard` 认证用户结束

### 路由解析
路由解析处于 `Laravel` 应用生命周期的的第三阶段（请求处理）。在第二阶段结束，第三阶段开始时，`request` 进行了重绑定：

```php
// Illuminate\Foundation\Http\Kernel

// request 重绑定的发生过程

// HTTP kernel 捕获request，开始处理
public function handle($request)
{
    try {
        $request->enableHttpMethodParameterOverride();

        $response = $this->sendRequestThroughRouter($request);
    } catch (Exception $e) {
        $this->reportException($e);

        $response = $this->renderException($request, $e);
    } catch (Throwable $e) {
        $this->reportException($e = new FatalThrowableError($e));

        $response = $this->renderException($request, $e);
    }

    $this->app['events']->dispatch(
        new Events\RequestHandled($request, $response)
    );

    return $response;
}

// 2. HTTP kernel 发送request通过路由
protected function sendRequestThroughRouter($request)
{
    // 这里第一次对request经行绑定，但不会触发重绑定事件
    $this->app->instance('request', $request);
    // 紧接着立刻清除已绑定的request对象
    Facade::clearResolvedInstance('request');

    $this->bootstrap();

    return (new Pipeline($this->app))
        ->send($request)
        ->through($this->app->shouldSkipMiddleware() ? [] : $this->middleware)
        ->then($this->dispatchToRouter());
}

// 3. HTTP kernel 准备解析路由
protected function dispatchToRouter()
{
    return function ($request) {
        // request 在这里重新被绑定，触发重绑定事件
        $this->app->instance('request', $request);

        return $this->router->dispatch($request);
    };
}
```

框架选择在此处对 `request` 进行重绑定，是因为，刚刚结束的第二阶段，已经完成了所有服务提供者的注册与启动，此时容器中已经存在所有的服务对象，通过服务对象来丰富 `request` 对象成为可能。

在 `request` 重新绑定之后，执行了这么一段代码：

```php
// Illuminate\Auth\AuthServiceProvider

return $this->router->dispatch($request);
```

这段代码的后文比较长，我简单概况一下：

```
匹配并命中路由 -> 通过路由解析并实例化控制器对象 -> 收集路由与控制器中定义的中间件 -> 执行路由中间件 -> 执行控制器方法
```

### 路由中间件
认证的行为，在中间件中触发。触发认证行为的中间件是`\Illuminate\Auth\Middleware\Authenticate::class`，在 `request` 通过该中间件时，`Guard` 检查 `request` 是否已通过认证，通过则放行，否则抛出`AuthenticationException`未认证异常。在通过认证之后，用户实例会保存在 `Guard` 对象中，后续所有找 `Guard` 要用户的行为，都可以得到相同的用户实例。至此，认证完成。

综上所述，用户的认证，发生在执行路由中间的过程中，在此之前，是无法通过 `Auth` 来获取认证用户的，需要特别注意的是，控制器的实例化过程，发生在路由中间件执行之前，所以无法在控制器的构造函数中获取用户的登陆状态。
