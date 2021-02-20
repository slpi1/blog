---
title: Laravel 中间件
date: 2019-12-24 11:25
tags:
- PHP
- Laravel
---

# Index
 - [中间件全家福](#中间件全家福)
 - [Authenticate](#authenticate)
 - [CheckForMaintenanceMode](#checkformaintenancemode)
 - [EncryptCookies](#encryptcookies)
 - [RedirectIfAuthenticated](#redirectifauthenticated)
 - [TrimStrings](#trimstrings)
 - [TrustProxies](#trustproxies)
 - [VerifyCsrfToken](#verifycsrftoken)
 - 待续...
 - [ValidatePostSize](#validatepostsize)
 - [ConvertEmptyStringsToNull](#convertemptystringstonull)
 - [AddQueuedCookiesToResponse](#addqueuedcookiestoresponse)
 - [StartSession](#startsession)
 - [ShareErrorsFromSession](#shareerrorsfromsession)
 - [SubstituteBindings](#substitutebindings)
 - [AuthenticateWithBasicAuth](#authenticatewithbasicauth)
 - [Authorize](#authorize)
 - [RedirectIfAuthenticated](#redirectifauthenticated)
 - [ThrottleRequests](#throttlerequests)

## 中间件全家福
先在这里列一下框架中用到的中间件。

```php
/**
 * 全局中间件
 */

// 检测项目是否处于 维护模式。
\Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class

// 检查请求数据大小是否超过限制
\Illuminate\Foundation\Http\Middleware\ValidatePostSize::class

// 清理请求字段值首位的空格
\App\Http\Middleware\TrimStrings::class

// 将请求中的空字段转化为null值
\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class

// 设置可信任代理
\App\Http\Middleware\TrustProxies::class

/**
 * web 路由中间件
 */
// 获取请求中的Cookies，解密Cookies的值，加密Headers中的Cookies
\App\Http\Middleware\EncryptCookies::class

// 添加Cookies到Headers
\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class

// 启用Session，在Headers中添加Session相关的Cookies
\Illuminate\Session\Middleware\StartSession::class

// 将闪存到Session中的错误信息，共享到视图 - 闪存是指在上一次请求时将数据存入Session的过程，数据会在下一次请求时取出并销毁
\Illuminate\View\Middleware\ShareErrorsFromSession::class

// csrf 令牌验证
\App\Http\Middleware\VerifyCsrfToken::class

// 路由参数模型绑定检查
\Illuminate\Routing\Middleware\SubstituteBindings::class

/**
 * 其他路由中间件
 */
[
    // 认证中间件
    'auth'       => \Illuminate\Auth\Middleware\Authenticate::class,

    // http基础认证
    'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,

    // 路由参数模型绑定检查
    'bindings'   => \Illuminate\Routing\Middleware\SubstituteBindings::class,

    // 授权检查
    'can'        => \Illuminate\Auth\Middleware\Authorize::class,

    // 访客认证
    'guest'      => \App\Http\Middleware\RedirectIfAuthenticated::class,

    // 请求节流
    'throttle'   => \Illuminate\Routing\Middleware\ThrottleRequests::class,
]
```

## Authenticate
   
### 源文件

`app\Http\Middleware\Http\Middleware\Authenticate.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
    * Get the path the user should be redirected to when they are not authenticated.
    *
    * @param \Illuminate\Http\Request $request
    * @return string
    */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
```

### 作用

用户身份验证。可修改 `redirectTo` 方法，返回未经身份验证的用户应该重定向到的路径。


## CheckForMaintenanceMode

### 源文件

`app\Http\Middleware\CheckForMaintenanceMode.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode as Middleware;

class CheckForMaintenanceMode extends Middleware
{
    /**
    * The URIs that should be reachable while maintenance mode is enabled.
    *
    * @var array
    */
    protected $except = [
        //
    ];
}
```

### 作用

检测项目是否处于 **维护模式**。可通过 `$except` 数组属性设置在维护模式下仍能访问的网址。


## EncryptCookies

### 源文件

`app\Http\Middleware\EncryptCookies.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
    * The names of the cookies that should not be encrypted.
    *
    * @var array
    */
    protected $except = [
        //
    ];
}
```

### 作用

对 Cookie 进行加解密处理与验证。可通过 `$except` 数组属性设置不做加密处理的 cookie。



## RedirectIfAuthenticated

### 源文件

`app\Http\Middleware\RedirectIfAuthenticated.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
    * Handle an incoming request.
    *
    * @param \Illuminate\Http\Request $request
    * @param \Closure $next
    * @param string|null $guard
    * @return mixed
    */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            return redirect('/home');
        }

        return $next($request);
    }
}
```

### 作用

当请求页是 `注册、登录、忘记密码` 时，检测用户是否已经登录，如果已经登录，那么就重定向到首页，如果没有就打开相应界面。可以在 `handle` 方法中定制重定向到的路径。


## TrimStrings

### 源文件

`app\Http\Middleware\TrimStrings.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
    * The names of the attributes that should not be trimmed.
    *
    * @var array
    */
    protected $except = [
        'password',
        'password_confirmation',
    ];
}
```

### 作用

对请求参数内容进行 **前后空白字符清理**。可通过 `$except` 数组属性设置不做处理的参数。


## TrustProxies

### 源文件

`app\Http\Middleware\TrustProxies.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
    * The trusted proxies for this application.
    *
    * @var array|string
    */
    protected $proxies;

    /**
    * The headers that should be used to detect proxies.
    *
    * @var int
    */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
```

### 作用

配置可信代理。可通过 `$proxies` 属性设置可信代理列表，`$headers` 属性设置用来检测代理的 HTTP 头字段。


## VerifyCsrfToken

### 源文件

`app\Http\Middleware\VerifyCsrfToken.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
    * Indicates whether the XSRF-TOKEN cookie should be set on the response.
    *
    * @var bool
    */
    protected $addHttpCookie = true;

    /**
    * The URIs that should be excluded from CSRF verification.
    *
    * @var array
    */
    protected $except = [
        //
    ];
}
```

### 作用

验证请求里的令牌是否与存储在会话中令牌匹配。可通过 `$except` 数组属性设置不做 CSRF 验证的网址。

## ValidatePostSize

### 源文件

`vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ValidatePostSize.php`

```php
<?php

namespace Illuminate\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\PostTooLargeException;

class ValidatePostSize
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Illuminate\Http\Exceptions\PostTooLargeException
     */
    public function handle($request, Closure $next)
    {
        $max = $this->getPostMaxSize();

        if ($max > 0 && $request->server('CONTENT_LENGTH') > $max) {
            throw new PostTooLargeException;
        }

        return $next($request);
    }

    /**
     * Determine the server 'post_max_size' as bytes.
     *
     * @return int
     */
    protected function getPostMaxSize()
    {
        if (is_numeric($postMaxSize = ini_get('post_max_size'))) {
            return (int) $postMaxSize;
        }

        $metric = strtoupper(substr($postMaxSize, -1));
        $postMaxSize = (int) $postMaxSize;

        switch ($metric) {
            case 'K':
                return $postMaxSize * 1024;
            case 'M':
                return $postMaxSize * 1048576;
            case 'G':
                return $postMaxSize * 1073741824;
            default:
                return $postMaxSize;
        }
    }
}

```

### 作用
检查请求数据大小是否操作限制。 

## ConvertEmptyStringsToNull

### 源文件

`vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php`
```php
<?php

namespace Illuminate\Foundation\Http\Middleware;

class ConvertEmptyStringsToNull extends TransformsRequest
{
    /**
     * Transform the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function transform($key, $value)
    {
        return is_string($value) && $value === '' ? null : $value;
    }
}

```

### 作用
将请求中的空字符，转化为 `NULL` 值。

## AddQueuedCookiesToResponse

### 源文件
`vendor/laravel/framework/src/Illuminate/Cookie\Middleware/AddQueuedCookiesToResponse.php`

```php
<?php

namespace Illuminate\Cookie\Middleware;

use Closure;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;

class AddQueuedCookiesToResponse
{
    /**
     * The cookie jar instance.
     *
     * @var \Illuminate\Contracts\Cookie\QueueingFactory
     */
    protected $cookies;

    /**
     * Create a new CookieQueue instance.
     *
     * @param  \Illuminate\Contracts\Cookie\QueueingFactory  $cookies
     * @return void
     */
    public function __construct(CookieJar $cookies)
    {
        $this->cookies = $cookies;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        foreach ($this->cookies->getQueuedCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
```

### 作用
将程序中通过 `Cookies Queue` 的方式设置的 `cookies` 值设置到响应头。

## StartSession

### 源文件

`vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php`

```php
<?php

namespace Illuminate\Session\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Session\SessionManager;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\CookieSessionHandler;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class StartSession
{
    /**
     * The session manager.
     *
     * @var \Illuminate\Session\SessionManager
     */
    protected $manager;

    /**
     * Indicates if the session was handled for the current request.
     *
     * @var bool
     */
    protected $sessionHandled = false;

    /**
     * Create a new session middleware.
     *
     * @param  \Illuminate\Session\SessionManager  $manager
     * @return void
     */
    public function __construct(SessionManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->sessionHandled = true;

        // If a session driver has been configured, we will need to start the session here
        // so that the data is ready for an application. Note that the Laravel sessions
        // do not make use of PHP "native" sessions in any way since they are crappy.
        if ($this->sessionConfigured()) {
            $request->setLaravelSession(
                $session = $this->startSession($request)
            );

            $this->collectGarbage($session);
        }

        $response = $next($request);

        // Again, if the session has been configured we will need to close out the session
        // so that the attributes may be persisted to some storage medium. We will also
        // add the session identifier cookie to the application response headers now.
        if ($this->sessionConfigured()) {
            $this->storeCurrentUrl($request, $session);

            $this->addCookieToResponse($response, $session);
        }

        return $response;
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        if ($this->sessionHandled && $this->sessionConfigured() && ! $this->usingCookieSessions()) {
            $this->manager->driver()->save();
        }
    }

    /**
     * Start the session for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Session\Session
     */
    protected function startSession(Request $request)
    {
        return tap($this->getSession($request), function ($session) use ($request) {
            $session->setRequestOnHandler($request);

            $session->start();
        });
    }

    /**
     * Get the session implementation from the manager.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Session\Session
     */
    public function getSession(Request $request)
    {
        return tap($this->manager->driver(), function ($session) use ($request) {
            $session->setId($request->cookies->get($session->getName()));
        });
    }

    /**
     * Remove the garbage from the session if necessary.
     *
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @return void
     */
    protected function collectGarbage(Session $session)
    {
        $config = $this->manager->getSessionConfig();

        // Here we will see if this request hits the garbage collection lottery by hitting
        // the odds needed to perform garbage collection on any given request. If we do
        // hit it, we'll call this handler to let it delete all the expired sessions.
        if ($this->configHitsLottery($config)) {
            $session->getHandler()->gc($this->getSessionLifetimeInSeconds());
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param  array  $config
     * @return bool
     */
    protected function configHitsLottery(array $config)
    {
        return random_int(1, $config['lottery'][1]) <= $config['lottery'][0];
    }

    /**
     * Store the current URL for the request if necessary.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @return void
     */
    protected function storeCurrentUrl(Request $request, $session)
    {
        if ($request->method() === 'GET' && $request->route() && ! $request->ajax()) {
            $session->setPreviousUrl($request->fullUrl());
        }
    }

    /**
     * Add the session cookie to the application response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Contracts\Session\Session  $session
     * @return void
     */
    protected function addCookieToResponse(Response $response, Session $session)
    {
        if ($this->usingCookieSessions()) {
            $this->manager->driver()->save();
        }

        if ($this->sessionIsPersistent($config = $this->manager->getSessionConfig())) {
            $response->headers->setCookie(new Cookie(
                $session->getName(), $session->getId(), $this->getCookieExpirationDate(),
                $config['path'], $config['domain'], $config['secure'] ?? false,
                $config['http_only'] ?? true, false, $config['same_site'] ?? null
            ));
        }
    }

    /**
     * Get the session lifetime in seconds.
     *
     * @return int
     */
    protected function getSessionLifetimeInSeconds()
    {
        return ($this->manager->getSessionConfig()['lifetime'] ?? null) * 60;
    }

    /**
     * Get the cookie lifetime in seconds.
     *
     * @return \DateTimeInterface
     */
    protected function getCookieExpirationDate()
    {
        $config = $this->manager->getSessionConfig();

        return $config['expire_on_close'] ? 0 : Carbon::now()->addMinutes($config['lifetime']);
    }

    /**
     * Determine if a session driver has been configured.
     *
     * @return bool
     */
    protected function sessionConfigured()
    {
        return ! is_null($this->manager->getSessionConfig()['driver'] ?? null);
    }

    /**
     * Determine if the configured session driver is persistent.
     *
     * @param  array|null  $config
     * @return bool
     */
    protected function sessionIsPersistent(array $config = null)
    {
        $config = $config ?: $this->manager->getSessionConfig();

        return ! in_array($config['driver'], [null, 'array']);
    }

    /**
     * Determine if the session is using cookie sessions.
     *
     * @return bool
     */
    protected function usingCookieSessions()
    {
        if ($this->sessionConfigured()) {
            return $this->manager->driver()->getHandler() instanceof CookieSessionHandler;
        }

        return false;
    }
}

```

### 作用
启用 `session`。同时执行 `session` 垃圾回收、 `referer` 保存，`session ID` 保存至 `cookies` 等操作。

## ShareErrorsFromSession

### 源文件

`vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php`

```php
<?php

namespace Illuminate\View\Middleware;

use Closure;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Contracts\View\Factory as ViewFactory;

class ShareErrorsFromSession
{
    /**
     * The view factory implementation.
     *
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $view;

    /**
     * Create a new error binder instance.
     *
     * @param  \Illuminate\Contracts\View\Factory  $view
     * @return void
     */
    public function __construct(ViewFactory $view)
    {
        $this->view = $view;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // If the current session has an "errors" variable bound to it, we will share
        // its value with all view instances so the views can easily access errors
        // without having to bind. An empty bag is set when there aren't errors.
        $this->view->share(
            'errors', $request->session()->get('errors') ?: new ViewErrorBag
        );

        // Putting the errors in the view for every view allows the developer to just
        // assume that some errors are always available, which is convenient since
        // they don't have to continually run checks for the presence of errors.

        return $next($request);
    }
}

```

### 作用
将闪存到 `session` 中的错误消息数据，传递到视图中。

## SubstituteBindings
### 源文件

```php
<?php

namespace Illuminate\Routing\Middleware;

use Closure;
use Illuminate\Contracts\Routing\Registrar;

class SubstituteBindings
{
    /**
     * The router instance.
     *
     * @var \Illuminate\Contracts\Routing\Registrar
     */
    protected $router;

    /**
     * Create a new bindings substitutor.
     *
     * @param  \Illuminate\Contracts\Routing\Registrar  $router
     * @return void
     */
    public function __construct(Registrar $router)
    {
        $this->router = $router;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->router->substituteBindings($route = $request->route());

        $this->router->substituteImplicitBindings($route);

        return $next($request);
    }
}

```

### 作用
检查路由模型绑定。

## AuthenticateWithBasicAuth
### 源文件

```php
<?php

namespace Illuminate\Auth\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class AuthenticateWithBasicAuth
{
    /**
     * The guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        return $this->auth->guard($guard)->basic() ?: $next($request);
    }
}

```

### 作用
`http` 基础认证，浏览器弹出账号密码输入框。

## Authorize

### 源文件

```php
<?php

namespace Illuminate\Auth\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;

class Authorize
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * The gate instance.
     *
     * @var \Illuminate\Contracts\Auth\Access\Gate
     */
    protected $gate;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @param  \Illuminate\Contracts\Auth\Access\Gate  $gate
     * @return void
     */
    public function __construct(Auth $auth, Gate $gate)
    {
        $this->auth = $auth;
        $this->gate = $gate;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $ability
     * @param  array|null  $models
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle($request, Closure $next, $ability, ...$models)
    {
        $this->auth->authenticate();

        $this->gate->authorize($ability, $this->getGateArguments($request, $models));

        return $next($request);
    }

    /**
     * Get the arguments parameter for the gate.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array|null  $models
     * @return array|string|\Illuminate\Database\Eloquent\Model
     */
    protected function getGateArguments($request, $models)
    {
        if (is_null($models)) {
            return [];
        }

        return collect($models)->map(function ($model) use ($request) {
            return $model instanceof Model ? $model : $this->getModel($request, $model);
        })->all();
    }

    /**
     * Get the model to authorize.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $model
     * @return \Illuminate\Database\Eloquent\Model|string
     */
    protected function getModel($request, $model)
    {
        return $this->isClassName($model) ? $model : $request->route($model);
    }

    /**
     * Checks if the given string looks like a fully qualified class name.
     *
     * @param  string  $value
     * @return bool
     */
    protected function isClassName($value)
    {
        return strpos($value, '\\') !== false;
    }
}

```

### 作用
`Gate` 授权检查

## RedirectIfAuthenticated
### 源文件

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            return redirect('/home');
        }

        return $next($request);
    }
}

```

### 作用
访客授权检查。仅在用户未登录的情况下通过，否则重定向到统一的地址。

## ThrottleRequests
### 源文件

```php
<?php

namespace Illuminate\Routing\Middleware;

use Closure;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\InteractsWithTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ThrottleRequests
{
    use InteractsWithTime;

    /**
     * The rate limiter instance.
     *
     * @var \Illuminate\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * Create a new request throttler.
     *
     * @param  \Illuminate\Cache\RateLimiter  $limiter
     * @return void
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);

        $maxAttempts = $this->resolveMaxAttempts($request, $maxAttempts);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts, $decayMinutes)) {
            throw $this->buildException($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decayMinutes);

        $response = $next($request);

        return $this->addHeaders(
            $response, $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve the number of attempts if the user is authenticated or not.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|string  $maxAttempts
     * @return int
     */
    protected function resolveMaxAttempts($request, $maxAttempts)
    {
        if (Str::contains($maxAttempts, '|')) {
            $maxAttempts = explode('|', $maxAttempts, 2)[$request->user() ? 1 : 0];
        }

        return (int) $maxAttempts;
    }

    /**
     * Resolve request signature.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     * @throws \RuntimeException
     */
    protected function resolveRequestSignature($request)
    {
        if ($user = $request->user()) {
            return sha1($user->getAuthIdentifier());
        }

        if ($route = $request->route()) {
            return sha1($route->getDomain().'|'.$request->ip());
        }

        throw new RuntimeException(
            'Unable to generate the request signature. Route unavailable.'
        );
    }

    /**
     * Create a 'too many attempts' exception.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function buildException($key, $maxAttempts)
    {
        $retryAfter = $this->getTimeUntilNextRetry($key);

        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );

        return new HttpException(
            429, 'Too Many Attempts.', null, $headers
        );
    }

    /**
     * Get the number of seconds until the next retry.
     *
     * @param  string  $key
     * @return int
     */
    protected function getTimeUntilNextRetry($key)
    {
        return $this->limiter->availableIn($key);
    }

    /**
     * Add the limit header information to the given response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int|null  $retryAfter
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $response->headers->add(
            $this->getHeaders($maxAttempts, $remainingAttempts, $retryAfter)
        );

        return $response;
    }

    /**
     * Get the limit headers information.
     *
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int|null  $retryAfter
     * @return array
     */
    protected function getHeaders($maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if (! is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = $this->availableAt($retryAfter);
        }

        return $headers;
    }

    /**
     * Calculate the number of remaining attempts.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int|null  $retryAfter
     * @return int
     */
    protected function calculateRemainingAttempts($key, $maxAttempts, $retryAfter = null)
    {
        if (is_null($retryAfter)) {
            return $this->limiter->retriesLeft($key, $maxAttempts);
        }

        return 0;
    }
}

```

### 作用
请求节流。限定单位时间内，同一客户端访问的评率。