---
title: php基础 - 时间与日期
date: 2015-09-24 12:30
description: php基础，时间与日期，常用时间日期函数以及时间日期对象。
keywords: php基础 - 时间与日期
categories:
- PHP
---

### 【时间与日期】 ###
#### 日期和时间库 ####
**1. 验证日期**
`boolean checkdate(int month, int day, int year)`：验证日期是否有效。

**2. 格式化日期和时间**

`string date( string format [, int timestamp])`：根据预定义格式指令，来将时间戳时间日期化为字符串形式。

**3. 友好化时间戳**
`array getdate([int timestamp])`：返回一个根据 timestamp 得出的包含有日期信息的结合数组。如果没有给出时间戳则认为是当前本地时间。


**4. 处理时间戳**
- 确定当前时间戳：`int time()`
- 根据特定时间创建时间戳：`int mktime ([ int $hour [, int $minute [, int $second [, int $month [, int $day [, int $year [, int $is_dst ]]]]]]] )`

**5. 将时间描述转化为时间戳**
`int strtotime ( string $time [, int $now ] )`：将任何英文文本的日期时间描述解析为 Unix 时间戳。

#### DateTime构造函数 ####
**1. 创建日起对象**
- 面向对象风格:`public DateTime::__construct() ([ string $time = "now" [, DateTimeZone $timezone = NULL ]] )`：例如，`$d1=new DateTime("2012-07-08 11:14:15.638276");`
- 过程化风格:`DateTime date_create ([ string $time = "now" [, DateTimeZone $timezone = NULL ]] )`

**2. 格式化日期**
- 面向对象风格:`public string DateTime::format ( string $format )`
- 过程化风格:`string date_format( DateTime $object , string $format )`

**3. 获取时间戳**
- 面向对象风格：`public int DateTime::getTimestamp ( void )`
- 过程化风格：`int date_timestamp_get ( DateTime $object )`

**4. 设置日期**
- 面向对象风格：`public DateTime DateTime::setDate ( int $year , int $month , int $day )`
- 过程化风格：`DateTime date_date_set ( DateTime $object , int $year , int $month , int $day )`

**5. 设置时间**
- 面向对象风格：`public DateTime DateTime::setTime ( int $hour , int $minute [, int $second = 0 ] )`
- 过程化风格：`DateTime date_time_set ( DateTime $object , int $hour , int $minute [, int $second = 0 ] )`

**6. 设置时间戳**
- 面向对象风格：`public DateTime DateTime::setTimestamp ( int $unixtimestamp )`
- 过程化风格：`DateTime date_timestamp_set ( DateTime $object , int $unixtimestamp )`