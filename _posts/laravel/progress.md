---
title: laravel 主流程
date: 2019-05-06 11:26
tag: laravel
categories:
- PHP
- Laravel
---

```flow
st=>start: index.php入口
e=>end: 结束
define_start_time=>operation: 定义开始运行时间LARAVEL_START
autoload=>operation: composer自动加载
load_app=>condition: 框架载入
instance_application=>condition: 实例化容器
Application::__construct

app_base_binding=>operation: 容器自绑定
app_base_provider=>operation: 容器基础provider注册
Route/Log/Event
app_base_alias=>operation: 容器核心别名定义

bind_base_kernel=>operation: 单例绑定容器核心服务
HttpKernel
ConsoleKernel
ExceptionsHandler

make_http_kernel=>operation: 实例化http处理器
capture_http=>operation: 捕获http请求
kernel_handle_request=>condition: http处理器处理请求
bootstrap=>condition: 容器启动
bootstrap_app=>operation: 加载环境变量配置
加载配置文件
注册异常handler
注册Facades
注册应用providers
启动应用proveider

common_middleware=>condition: 全局中间件过滤
dispatch_route=>operation: 解析路由
match_route=>operation: 匹配路由
instance_controller=>operation: 实例化控制器
route_middleware=>condition: 路由中间件过滤
call_action=>operation: callAction引导执行控制器方法
run_action=>operation: 执行控制器

common_middleware_item=>operation: 全局中间件详情
up&down检查
post包大小检查
空格过滤
空数据转化为null
代理设置

web_route_middleware_item=>operation: 路由中间件详情
cookie加密解密
cookie设置
start session
Error闪存至session
csrf验证
路由模型绑定监听

send_response=>operation: 发送响应
kernel_terminate=>operation: http处理器运行后台任务

st->define_start_time->autoload->load_app
load_app(yes,right)->instance_application
load_app(no,)->make_http_kernel->capture_http->kernel_handle_request
instance_application(no)->bind_base_kernel(left)->make_http_kernel
instance_application(yes, right)->app_base_binding->app_base_provider->app_base_alias(left)->bind_base_kernel
kernel_handle_request(no)->send_response->kernel_terminate->e
kernel_handle_request(yes, right)->bootstrap
bootstrap(no)->common_middleware
bootstrap(yes, right)->bootstrap_app
common_middleware(no)->dispatch_route->match_route->instance_controller->route_middleware
common_middleware(yes, right)->common_middleware_item
route_middleware(no)->call_action->run_action(left)->send_response
route_middleware(yes, right)->web_route_middleware_item
```