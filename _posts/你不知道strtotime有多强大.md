---
title: 你不知道strtotime有多强大
date: 2019-10-17 11:12
---

关于这个函数，PHP 手册译本是这样描述的：
> ** strtotime **
>
>(PHP 4, PHP 5, PHP 7)
>strtotime — 将任何字符串的日期时间描述解析为 Unix 时间戳
>
> ** 说明 **
>
> `int strtotime ( string $time [, int $now = time() ] )`
>本函数预期接受一个包含美国英语日期格式的字符串并尝试将其解析为 Unix 时间戳（自 January 1 1970 00:00:00 GMT 起的秒数），其值相对于 `now` 参数给出的时间，如果没有提供此参数则用系统当前时间。

原文：
> ** strtotime **
>
>(PHP 4, PHP 5, PHP 7)
>strtotime — Parse about any English textual datetime description into a Unix timestamp
>
> ** Description **
>
> `int strtotime ( string $time [, int $now = time() ] )`
>The function expects to be given a string containing an English date format and will try to parse that format into a Unix timestamp (the number of seconds since January 1 1970 00:00:00 UTC), relative to the timestamp given in now, or the current time if now is not supplied.

先按下不表。

关于PHP 中时间格处理，几乎是随处都会用到。在项目中就遇到过：
```
//判断是否为时间格式
function isTimeFormat($str)
{
    return strtotime($str) !== false;
}
```
一开始并未深究其中逻辑，因为他工作挺正常的。直到最近发现多处日期数据有误，追踪到上述一段代码，才发现并没有这么简单。这段代码是如何引入的呢，通过搜索后发现，网上有很多文章都提到过这一黑科技：[案例](http://www.daimajiayuan.com/sitejs-17065-1.html)、[案例](http://www.jb51.net/article/44817.htm)、[案例](https://zhidao.baidu.com/question/560995893965912404.html)。所以我猜想应该也是在网上查询后加进来的。但是网上找到的案例或多或少都会提到该方法不是特别严格，对于某些特别的情况判断会出错。

<!-- more -->

## 什么情况下适用？ ##
这个方法是用来判断日期格式是否正确，说的具体一点，就是用户输入的日期格式是否正确。如果是通过标准Unix 时间戳转化的日期格式，strtotime 函数是肯定可以正常工作的（废话）。如果是用户随意输入的格式，那还真不好说，不过在输入日期格式的时候，能经过相关插件过滤的话，这个问题也基本不会存在了。以目前前端插件的丰富程度来看，用户手动输入日期的场景应该是不存在的，都是通过插件进行选择，那么可以说，其实这段代码可以寿终正寝了。

## 问题场景还原 ##
```
$time = time();
if(isTimeFormat($time))
{
    $date = date('Ymd',strtotime($time));
}
else
{
    $date = date('Ymd') ;
}
```
项目中出问题的细节基本如此，所以问题的关键在于 `strtotime(strtotime(time()))` 的返回，根据时间戳`time()`的不同，他的返回值还真是不能确定：
```
var_dump(strtotime(strtotime('2017-07-18 08:36:39')));
//bool(false)

var_dump(strtotime(strtotime('2017-07-18 08:36:40')));
//int(158748735636)

var_dump(strtotime(strtotime('2017-07-18 12:36:40')));
//int(-17970195562)
```
所以问题在于运用本身就错了，在原始场景下，获取到时间戳后，直接格式化即可，不必进行时间格式是否正确的判断。

## strtotime 到底有多强大？ ## 
其实根据函数描述来看，strtotime 本意应该是用来转化**对一个时间的正确描述**为时间戳。远远不到**任何字符串**的程度。所以，以后在使用strtotime 函数的时候，应该建立在一定的输入预期上，或者是可控制的输入上。而不要给他一些奇奇怪的东西。比如：
```
strtotime('2017-13-14');
strtotime('my birthday');
strtotime('hello kitty');
```
什么的。。