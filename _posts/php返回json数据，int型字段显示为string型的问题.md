---
title: php返回json数据，int型字段显示为string型的问题
---

开发过程中遇到，同一个接口在不同环境下返回格式不一致的问题。由于前端使用TypeScript开发，对数据类型敏感，本来应该是int型的数据，接口返回格式表示却为string型，导致运行报错。

由于在本地测试没有问题，在服务端返回错误，基本确定为开发环境导致的错误。通过在网上搜索相关类型的问题，得出是 `mysql` 引擎返回数据时导致的问题。
可能导致该问题的原因有：
- `PDO` 进行链接时的参数设置：`ATTR_EMULATE_PREPARES = false`, `ATTR_STRINGIFY_FETCHES = false`
- `php` 扩展安装不正确：`php-mysql` 扩展换成 `php-mysqlnd`

本人遇到的情况属于第二种，第一种情况并未做进一步测试，替换成功后问题解决。
```
yum remove php71w-mysql && yum install -y php71w-mysqlnd
```