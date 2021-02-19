---
title: laravel 路由模块源码分析
date: 2020-01-09 16:10
---

# 概述
- 路由模块是如何进入系统生命周期的？
- 路由模块包含哪些功能，分别由哪些子模块负责？

需要说明的是，路由基本是针对web应用而言的，所以本文中提及的应用，是指由laravel构建的web应用，而不包括Console应用。

# 路由模块的生命周期
在由laravel构建的应用当中，功能模块可以统称为服务，所以路由模块也可以称为路由服务。由laravel文档可知，应用中的服务都是由服务提供者来提供的，路由模块也是，所以找到了路由服务提供者，就等于找到了路由模块生命周期的入口。

如果熟悉laravel应用的生命周期的话，就应该会很清楚：在容器的初始化阶段，会注册三个基础服务提供者，分别是`EventServiceProvider/LogServiceProvider/RoutingServiceProvider`，其中，`RoutingServiceProvider`即是路由服务提供者；接着，在容器启动阶段，分别完成`配置加载/注册配置中的服务提供者/启动服务提供者`三项事务，而在注册配置中的服务提供者时，又会注册一个叫做`RouteServiceProvider`的路由服务提供者；然后，在启动服务提供者阶段，完成路由启动。之后进入应用执行阶段，期间路由服务由开发者自行决定如何使用。

所以，路由模块的生命周期可以简单归纳为三个阶段：
- 路由服务注册阶段，由RoutingServiceProvider类接管，主要负责路由模块各个功能组件的注册。
- 路由启动阶段，加载开发者定义的路由，由RouteServiceProvider类接管。
- 路由应用阶段，不是本文所述重点，可以参考官方手册。

# 路由模块的组成
路由模块在应用中的命令空间名称是 `Illuminate\Routing`。在路由服务的注册阶段，服务提供者注册了7个对象到容器当中：
- router: 路由核心
- url：url生成器
- redirect：url跳转服务
- ServerRequestInterface：略
- ResponseInterface：略
- ResponseFactoryContract：略
- ControllerDispatcherContract：控制器解析器
这些就是路由模块中的重要组件啦，当然，各个组件还会进行细分，接下来我们会分别对各个组件做出说明。

## 路由核心
- 类名: Illuminate\Routing\Router::class
- 功能：路由定义/路由匹配
- 子组件：Route/RouteCollection/RouteRegistrar/ResourceRegistrar

在具体介绍路由核心的功能之前，我们先了解一下核心子组件，对他们有一个大致的了解。

### Route
- 类名：Illuminate\Routing\Route::class
- 功能：路由实例/控制器参数解析/控制器中间件解析

Route就是我们将要定义的一个一个的路由，我们通过命令`php artisan route:list`列出来的，就是它们了，与Router只有一字之差，但功能大相径庭。在laravel文档 `基础功能 - 路由` 篇中，主要讲的就是Route，由于文档对它有大篇幅的解释，所以，他不是本文的主角。

### RouteCollection
- 类名：Illuminate\Routing\RouteCollection::class
- 功能：路由集合/路由表/路由匹配

RouteCollection是一个很直观的名字了，应用中无论在何处定义的路由，最后都会被添加到路由表中，因为RouteCollection的存在，所以`route:list`命令可以很方便的列出应用中定义的路由，也正是因为它的存在，在收到请求是，可以方便的识别出是请求的哪个路由。

### RouteRegistrar
- 类名：Illuminate\Routing\RouteRegistrar::class
- 功能：路由定义代理

简单来讲，当我们在`routes/web.php`文件中定义路由的时候，有可能是Router在定义路由，也有可能是RouteRegistrar在定义路由。

### ResourceRegistrar
- 类名：Illuminate\Routing\ResourceRegistrar::class
- 功能：路由定义代理（资源路由）

与RouteRegistrar的定位相同，只不过是专门处理资源路由定义时的场景。

### 功能
#### 路由定义
在laravel文档 `基础功能 - 路由` 篇中详细说明了如何在应用中定义路由，那么它背后是如何实现的呢？通过上文"路由模块的生命周期"的介绍，我们知道，路由的定义是在路由启动阶段，由RouteServiceProvider类负责。

RouteServiceProvider服务提供者启动时，会检查路由表是否被缓存，是的话会从缓存中直接返回路由表；否则会执行map方法，分别从`routes/web.php`和`routes/api.php`路由定义文件中，导入开发者定义的路由。它执行过程如下：

注：Route门面最终会指向Router对象，为了避免引起混淆，路由定义过程中都以Router做说明。

```php
Router::middleware('web')
     ->namespace($this->namespace)
     ->group(base_path('routes/web.php'));
```

而`routes/web.php`中的内容可能会出现以下片段:

```php
Router::group(['middleware' => ['auth']], function(){
    ...
});
```

很容易发现`Router`两次调用`group`时，方法的参数个数不一致，其实，Router并没有 
`middleware/as/domain/name/namespace/prefix` 这些方法，当调用这些方法时，会转发给RouteRegistrar路由定义代理对象去执行，在调用上述方法时，会把对应的参数收集起来，称为路由属性定义方法。而RouteRegistrar也由一个group方法，所以才说的通。

通过Router直接定义的路由，我称之为原生路由定义，而通过RouteRegistrar定义的路由，我称之为代理路由定义。在原生路由定义之下，又可以分为简单路由定义与复杂路由定义。简单路由定义指一次执行定义一个路由，复杂路由定义指一次执行可以定义多个路由，包括Router的`group/resource/apiResource`等方法。当RouteRegistrar定义路由时，又会转发给Router来进行定义，并收集路由属性定义链上的属性，所以，路由最终都是Router“直接”定义出来的，而RouteRegistrar和ResourceRegistrar都是路由定义中出现的语法糖。

||原生定义|代理定义|
|---|---|---|
|简单定义|Router::get($uri,$action)<br>Router::post($uri,$action)...|暂无该场景|
|复杂定义|Router::group($attribute,$callback/$routeFile)|RouteRegistrar::group($routeFile)|


#### 路由匹配
在容器启动完毕，请求通过全局中间件过滤后，会开始对请求的路由进行匹配关键方法是Illuminate\Routing\Router::findRoute

```php

protected function findRoute($request)
{
    // $this->routes 即是路由表，RouteCollection对象，$route既是命中的路由
    $this->current = $route = $this->routes->match($request);

    $this->container->instance(Route::class, $route);

    return $route;
}
```

Router调用findRoute方法，将请求request转发给RouteCollection的match方法执行，match方法接着会遍历路由对象，由路由对象与request进行匹配，匹配通过则命中路由，否则会抛出路由不存在的异常。匹配会从`uri/method/host/sheme`四个方面进行，全部通过为命中。

#### 控制器解析
控制器解析分为两个过程，控制器中间件解析和控制器参数解析，这两个过程都是在Route路由对象中执行，由Route转发给ControllerDispatcherContract的实例经行解析。

##### 控制器中间件解析
在路由匹配之前，request已经通过全局中间件，接下来request将要通过路由中间件，通过文档我们知道，路由中间件可以在定义路由时添加（group属性或middleware方法），也可以在控制器中通过middleware方法定义。为了实现这一功能，所以需要在request通过路由中间件之前，实例化控制器，并从中取出定义的中间件。这也导致了在控制器的构造方法中，无法获取路由中间件中获得的状态。

##### 控制器参数解析
控制器参数解析是注入的关键。在容器与注入的概念当中，依赖对象先注入到容器之中（或者是依赖对象的实例化方法），后续执行对象，可以根据执行方法参数的类型，从容器中解析出所需对象，然后将这些对象注入到执行方法中，完成依赖的注入。控制器参数解析，就是通过反射解析出执行方法的参数类型，后续过程交给容器解决即可。回到路由模块的生命周期上来，在request通过路由中间件之后，路由解析出控制器的参数，然后通过callAction方法引导控制器方法的执行，执行参数已经通过参数解析得到。这里的callAction方法，解决了上面控制器中间件解析中提到的问题：控制器的构造函数使用受到限制，可以通过callAction方法代替构造函数来执行一些通用操作。

## URL生成器

- 类名：Illuminate\Routing\UrlGenerator::class
- 功能：生成url/判断字符串是否是url

URL生成器可以生成的url大致分为两种，一种是普通url，一种是路由url。

### 普通URL
普通URL的生成有以下几个方法：

```
// 当前请求的完整URL
URL::full();

// 不含查询字符串的url
URL::current();

// referer
URL::previous();

URL::to('foo/bar', $parameters, $secure);
URL::secure('foo/bar', $parameters);
URL::asset('css/foo.css', $secure);
URL::secureAsset('css/foo.css');
```
### 路由URL

普通URL的生成有以下几个方法：

```
// 根据控制器及方法生成url
URL::action('NewsController@item', ['id'=>123]);
URL::action('Auth\AuthController@logout');
URL::action('FooController@method', $parameters, $absolute);

// 根据路由名称生成url
URL::route('foo', $parameters, $absolute);
```

### 判断字符串是否是url

```
URL::isValidUrl('http://example.com');
```

## 路由跳转

- 类名：Illuminate\Routing\Redirector::class
- 功能：url跳转

### 一般跳转

```
return Redirect::to('foo/bar');
return Redirect::to('foo/bar')->with('key', 'value');
return Redirect::to('foo/bar')->withInput(Input::get());
return Redirect::to('foo/bar')->withInput(Input::except('password'));
return Redirect::to('foo/bar')->withErrors($validator);
```

### 相对跳转

```
// 后退
return Redirect::back();

// 刷新
return Redirect::refresh();
```

### 路由跳转

```
// 重定向到命名路由（根据命名路由算出 URL）
return Redirect::route('foobar');
return Redirect::route('foobar', array('value'));
return Redirect::route('foobar', array('key' => 'value'));

// 重定向到控制器动作（根据控制器动作算出 URL）
return Redirect::action('FooController@index');
return Redirect::action('FooController@baz', array('value'));
return Redirect::action('FooController@baz', array('key' => 'value'));
```

### 授权记忆

在授权服务中，完成授权后跳回到来源地址的功能依赖这两个方法。

```

// 记住当前url并跳转到指定$path
return Redirect::guest($path);

// 跳转到guest中记住的地址，否则跳转到$path
return Redirect::intended($path);
```

