---
title: laravel应用执行流程
date: 2019-05-06 11:26
tag: laravel
categories:
- PHP
- Laravel
---

# Index
 - [应用入口](#应用入口)
    - [第一阶段：容器准备阶段](#第一阶段：容器准备阶段)
        - [$app对象实例化](#$app对象实例化)
        - [Illuminate\Contracts\Http\Kernel::class](#Illuminate\Contracts\Http\Kernel::class)
        - [Illuminate\Contracts\Console\Kernel::class](#Illuminate\Contracts\Console\Kernel::class)
        - [Illuminate\Contracts\Debug\ExceptionHandler::class](#Illuminate\Contracts\Debug\ExceptionHandler::class)
    - [第二阶段：容器启动阶段](#第二阶段：容器启动阶段)
        - [Http处理器捕获请求](#Http处理器捕获请求)
        - [启动容器](#启动容器)
        - [启动服务提供者](#启动服务提供者)
    - [第三阶段：请求处理阶段](#第三阶段：请求处理阶段)
        - [路由解析](#路由解析)
        - [全局中间件过滤](#全局中间件过滤)
        - [控制器实例化](#控制器实例化)
        - [路由中间件过滤](#路由中间件过滤)
        - [运行路由方法并响应](#运行路由方法并响应)
    - [第四阶段：terminate](#第四阶段：terminate)


## 应用入口
请求经过web服务器，路由到index.php入口文件。在入口文件中引入`vendor/autoload.php`和`bootstrap/app.php`文件。其中，前者是文件自动加载规则，后者是应用启动文件。

### 第一阶段：容器准备阶段
在该文件中，首先实例化应用核心容器`Illuminate\Foundation\Application`，即`$app`对象，然后单例绑定容器核心服务：
- `Illuminate\Contracts\Http\Kernel::class`
- `Illuminate\Contracts\Console\Kernel::class`
- `Illuminate\Contracts\Debug\ExceptionHandler::class`

#### $app对象实例化
- 实例化应用核心容器时，传入应用根目录作为参数。以此目录分别衍生出`base/lang/config/public/storage/database/resources/bootstrap`等应用目录
- 然后将应用对象绑定到自身`app`别名与`Illuminate\Container\Container::class`接口，同时绑定`PackageManifest::class`对象，后续用于对`composer.json`文件的解析
- 接下来注册基础服务提供者`EventServiceProvider/LogServiceProvider/RoutingServiceProvider`
- 最后进行核心别名的定义，

#### Illuminate\Contracts\Http\Kernel::class
该接口绑定http请求处理器。用于接收并处理来自web的请求

#### Illuminate\Contracts\Console\Kernel::class
该接口绑定Console处理器。用于接收来自CLI的命令。

#### Illuminate\Contracts\Debug\ExceptionHandler::class
该接口绑定异常处理器。用于在应用中出现异常时，做出对应的响应。

### 第二阶段：容器启动阶段
在做完第一阶段的准备工作后，从容器中解析出Http处理器，Http处理器捕获到请求，开始启动容器。

#### Http处理器捕获请求
捕获请求也就是`$request` 对象的初始化。

#### 启动容器
在捕获到请求后，需要先启动容器，容器启动的动作由Http处理器触发，并启动由Http处理器定义的启动文件，启动文件列表如下：

- `\Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class`: 加载环境变量配置.env文件
- `\Illuminate\Foundation\Bootstrap\LoadConfiguration::class`: 加载配置目录配置文件
- `\Illuminate\Foundation\Bootstrap\HandleExceptions::class`: 注册异常处理函数
- `\Illuminate\Foundation\Bootstrap\RegisterFacades::class`: 注册Facade，由`config/app.php`中定义的aliases和从composer.json中解析出的Facade组成
- `\Illuminate\Foundation\Bootstrap\RegisterProviders::class`: 注册服务提供者，由`config/app.php`中定义的providers和从composer.json中解析出的providers组成
- `\Illuminate\Foundation\Bootstrap\BootProviders::class`: 启动服务提供者。

#### 启动服务提供者

在启动服务提供者后，容器就算是完全准备就绪了：容器准备阶段的别名定义，提供了容器可对外服务的接口，而启动服务提供者的过程中，会实例化接口对应的对象，后续执行对象依赖接口时就可以从容器中解析出所需要的对象。

容器启动的重点，在于启动服务提供者，在该阶段完成了大量的基础重要工作。如：文件驱动服务、数据库连接服务、Session服务、Redis服务、视图服务、认证服务、路由服务等，详见`config/app.php`中的providers部分。其中，路由服务，会加载路由定义文件，生成路由表，供后续路由解析时做匹配查询。

在容器启动完毕后，进入第三阶段，请求处理阶段。

### 第三阶段：请求处理阶段

#### 路由解析
路由解析，就是根据当前请求，从路由表中匹配出定义好的路由，匹配会从四个方面进行`Uri/Method/Host/Scheme`。命中路由后进行下一阶段，否则抛出路由不存在的异常。

#### 全局中间件过滤
命中路由后，请求经过全局中间的过滤，全局中间定义在Http处理器中，应用默认全局中间件列表如下:
- `\Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class`: 检查应用状态
- `\Illuminate\Foundation\Http\Middleware\ValidatePostSize::class`： 检查请求数据大小
- `\App\Http\Middleware\TrimStrings::class`：过滤请求数据值中的首尾空字符
- `\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class`：将空的值转化成null
- `\App\Http\Middleware\TrustProxies::class`：配置代理IP

#### 控制器实例化
请求通过全局中间之后，接着就要实例化控制器。因为下一步就要通过路由中间件，路由中间件可能随路由一同定义在路由文件中，也有可能定义在控制器的$middleware属性中。所以，为了收集到完整的路由中间列表，需要先实例化控制器对象，才能从中解析中间件属性。由于控制器的实例化，与路由中间件的过滤存在这样一个顺序关系，导致在控制器的构造函数中，无法获取到在路由中间件中处理的一些状态。比如登录状态，用户认证过程，依赖`\Illuminate\Session\Middleware\StartSession::class|\App\Http\Middleware\EncryptCookies::class`等路由中间件的执行结果，控制器构造函数执行时，路由中间件还未执行，因此无法获取用户的登陆状态。

因此，不推荐在控制器的构造函数中做太多的逻辑处理，避免因上述原因导致的错误。如果确实有些场景，需要在构造函数中做一些统一的操作，可以用CallAction方法来代替构造函数，见`Illuminate\Routing\ControllerDispatcher::dispatch()`方法。
```
    /**
     * Dispatch a request to a given controller and method.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @param  mixed  $controller
     * @param  string  $method
     * @return mixed
     */
    public function dispatch(Route $route, $controller, $method)
    {
        $parameters = $this->resolveClassMethodDependencies(
            $route->parametersWithoutNulls(), $controller, $method
        );

        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters);
        }

        return $controller->{$method}(...array_values($parameters));
    }
```

#### 路由中间件过滤
一般路由中间件都会包含下列几个：

- `\App\Http\Middleware\EncryptCookies::class`: cookie加密与解密，解密是前置部分，加密是后置部分
- `\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class`：添加程序中定义的cookie到响应中。
- `\Illuminate\Session\Middleware\StartSession::class`：启动session
- `\Illuminate\View\Middleware\ShareErrorsFromSession::class`：将验证错误信息系添加到session中，见文档表单验证部分
- `\App\Http\Middleware\VerifyCsrfToken::class`：csrf检查
- `\Illuminate\Routing\Middleware\SubstituteBindings::class`：路由模型绑定解析

#### 运行路由方法并响应
进过上述处理之后，就终于到达了路由方法，也就是我们定义的路由中的控制器方法或闭包方法。执行完毕生成响应返回给浏览器。

需要注意的是，在执行完路由方法之后，还有可能存在后置中间件的方法待执行，比如cookie的加密，关于后置中间件的说明见文档中间件部分。

### 第四阶段：terminate
terminate是指在响应发送到浏览器之后会执行的方法。terminate方法中非常适合做日志记录的工作，可以完美解决使用log-viewer插件时的log死循环问题。其次，还需要注意的是，在web环境下与Console环境下，terminate方法的区别。在web环境下，Http处理器先执行中间件中定义的terminate方法，然后执行容器$app的terminate方法，而在Console环境下，直接执行$app的terminate方法。

