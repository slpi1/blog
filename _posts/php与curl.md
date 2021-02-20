---
title: php的curl使用
date: 2015-10-5 10:36
description: php，curl
keywords: php，curl
tags:
- PHP
---

在PHP 中可以通过curl 来进行系列网络请求操作。因为`allow_url_fopen`配置未开启而导致相关函数无法进行网络请求，可以用curl 来实现。

## curl 基本使用
以下代码演示了curl 发起请求的过程：
```
$ch = curl_init();
curl_setopt($ch, OPTION, value);
$content = curl_exec();
curl_close($ch);
```
其中OPTION的常用设置有（[详细说明](http://php.net/manual/en/function.curl-setopt.php)）：

* `CURLOPT_URL`  目标地址
* `CURLOPT_POSTFIELDS` 要传输的数据 http_build_query(array)
* `CURLOPT_RETURNTRANSFER` 获取的信息以字符串返回，而不是直接输出
* `CURLOPT_CONNECTTIMEOUT` 等待时长
* `CURLOPT_TIMEOUT` 请求时长
* `CURLOPT_NOBODY` 不输出 BODY 部分。同时 Mehtod 变成了 HEAD。可用来模拟get_headers


## curl 实现get 请求
```
$ch = @curl_init($url);
@curl_setopt($ch, CURLOPT_HEADER, false);
@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$string= @curl_exec($ch);
@curl_close($ch);
```

## curl 实现post 请求
```
$ch = @curl_init($url);
@curl_setopt($ch, CURLOPT_HEADER, false);
@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
@curl_setopt($ch, CURLOPT_POST, true);
@curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string); // http_build_query(array)
$string= @curl_exec($ch);
@curl_close($ch);
```

## curl 实现get_headers 函数
需要自己对返回的头信息做解析。
```
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);  // 输出头
curl_setopt($ch, CURLOPT_NOBODY, true);  // 忽略请求体
$info = curl_exec($ch);
curl_close($ch);
```

## curl 实现 request payload
用这种方式发送的数据以json的格式，而非一般的表单串形式（http_build_query）。
```
$ch = curl_init($url);
@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
     'Content-Type: application/json; charset=utf-8',
     'Content-Length: ' . strlen($data_string))
);
$info = curl_exec($ch);
curl_close($ch);
```