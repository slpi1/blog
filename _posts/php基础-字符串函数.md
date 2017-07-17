---
title: php基础 - 常用字符串函数
date: 2015-09-24 12:30
description: php基础，常用字符串函数
keywords: php基础 - 字符串函数
categories:
- PHP
---

## rtrim ##
删除字符串末端的空白字符（或者其他字符）。

## ltrim ##
删除字符串首端的空白字符（或者其他字符）。

## trim ##
去除字符串首尾处的空白字符（或者其他字符）

该函数删除 str 末端的空白字符并返回。不使用第二个参数，rtrim() 仅删除以下字符：

* " " (ASCII 32 (0x20))，普通空白符。
* "\t" (ASCII 9 (0x09))，制表符。
* "\n" (ASCII 10 (0x0A))，换行符。
* "\r" (ASCII 13 (0x0D))，回车符。
* "\0" (ASCII 0 (0x00))，NUL 空字节符。
* "\x0B" (ASCII 11 (0x0B))，垂直制表符。


## chr ##
返回相对应于 ascii 所指定的单个字符。 
```
<?php
    echo chr(65);
?>
```
输入
```
A
```

## ord ##
返回字符串 string 第一个字符的 ASCII 码值。该函数是 chr() 的互补函数。
```
<?php
    echo ord('A');
?>
```
输入
```
65
```


## explode ##
使用一个字符串分割另一个字符串。
```
array explode ( string $separator , string $string [, int $limit ] )
```
如果 separator 为空字符串（""），explode() 将返回 FALSE。如果 separator 所包含的值在 string 中找不到，那么 explode() 将返回包含 string 单个元素的数组。limit 表示最大分割的份数。

## implode ##
```
string implode ( string $glue , array $pieces )
string implode ( array $pieces )
```
组合字符串。

## parse_url ##
解析url字符串。返回url各个部分的信息，包括scheme,host,port,user,pass,path,query,fragment

* scheme:协议，http/ftp/file/https/mailto等
* host:主机名
* port：端口
* user：用户
* pass：密码
* path：路径
    * 对于绝对url，是com之后？之前的部分，一定以‘/’开头，以文档名结尾，或以‘/’结尾
    * 对于相对路径，是？之前的部分，可能以‘.’、‘..’、‘/’、字母开头。
* query：查询字符
* fragment：锚点

## parse_str ##
将`查询字符query`解析为值
```
<?php
$str = "first=value&arr[]=foo+bar&arr[]=baz";
parse_str($str);
echo $first;  // value
echo $arr[0]; // foo bar
echo $arr[1]; // baz

parse_str($str, $output);
echo $output['first'];  // value
echo $output['arr'][0]; // foo bar
echo $output['arr'][1]; // baz
?>
```

## str_repeat ##
重复一个字符串
```
string str_repeat ( string $input , int $multiplier )
```
返回 input 重复 multiplier 次后的结果。

## str_replace ##
子字符串替换，该函数区分大小写。使用 str_ireplace() 可以进行不区分大小写的替换。
```
mixed str_replace ( mixed $search , mixed $replace , mixed $subject [, int &$count ] )
```
该函数返回一个字符串或者数组。该字符串或数组是将 subject 中全部的 search 都被 replace 替换之后的结果。
* search 和 replace 为数组，那么 str_replace() 将对 subject 做二者的映射替换。
* replace 的值的个数少于 search 的个数，多余的替换将使用空字符串来进行。
* search 是一个数组而 replace 是一个字符串，那么 search 中每个元素的替换将始终使用这个字符串。该转换不会改变大小写。

## str_split ##
将字符串转换为数组
```
array str_split ( string $string [, int $split_length = 1 ] )
```
如果指定了可选的 split_length 参数，返回数组中的每个元素均为一个长度为 split_length 的字符块，否则每个字符块为单个字符。
如果 split_length 小于 1，返回 FALSE。如果 split_length 参数超过了 string 超过了字符串 string 的长度，整个字符串将作为数组仅有的一个元素返回。

## strip_tags ##
从字符串中去除 HTML 和 PHP 标记
```
string strip_tags ( string $str [, string $allowable_tags ] )
```

## stripos ##
查找字符串首次出现的位置。stripos() 不区分大小写。strpos()区分大小写。
```
int stripos ( string $haystack , string $needle [, int $offset = 0 ] )
```
返回在字符串 haystack 中 needle 首次出现的数字位置。 

## strripos ##
计算指定字符串在目标字符串中最后一次出现的位置（不区分大小写）。strrpos区分大小写。
```
int strripos ( string $haystack , string $needle [, int $offset = 0 ] )
```

## strstr ##
```
string strstr ( string $haystack , mixed $needle [, bool $before_needle = false ] )
```
同strchr，该函数区分大小写。如果想要不区分大小写，请使用 stristr()。 
查找字符串的首次出现。返回 haystack 字符串从 needle 第一次出现的位置开始到 haystack 结尾的字符串。
before_needle若为 TRUE，strstr() 将返回 needle 在 haystack 中的位置之前的部分。

## strrchr ##
查找指定字符在字符串中的最后一次出现
```
string strrchr ( string $haystack , mixed $needle )
```
该函数返回 haystack 字符串中的一部分，这部分以 needle 的最后出现位置开始，直到 haystack 末尾。
该函数没有不区分大小写的版本。

## strlen ##
获取字符串长度

## strtolower ##
将 string 中所有的字母字符转换为小写并返回。
## strtoupper ##
将 string 中所有的字母字符转换为大写并返回。

## ucfirst ##
将字符串的首字母转换为大写

## ucwords ##
将字符串中每个单词的首字母转换为大写

## strcmp ##
二进制安全字符串比较，strcasecmp不区分大小写
```
int strcmp ( string $str1 , string $str2 )
```
如果 str1 小于 str2，返回负数；如果 str1 大于 str2，返回正数；二者相等则返回 0。 

## strnatcmp ##
使用自然排序算法比较字符串。strnatcasecmp不区分大小写
```
int strnatcmp ( string $str1 , string $str2 )
```
该函数实现了以人类习惯对数字型字符串进行排序的比较算法，这就是“自然顺序”。

## strncmp ##
二进制安全比较字符串开头的若干个字符。strncasecmp不区分大小写
```
int strncmp ( string $str1 , string $str2 , int $len )
```
该函数与 strcmp() 类似，不同之处在于你可以指定两个字符串比较时使用的长度（即最大比较长度）。

## substr ##
返回字符串的子串
```
string substr ( string $string , int $start [, int $length ] )
```
参数描述string必需。规定要返回其中一部分的字符串。start必需。规定在字符串的何处开始。

* 正数 - 在字符串的指定位置开始
* 负数 - 在从字符串结尾的指定位置开始
* 0 - 在字符串中的第一个字符处开始

length可选。规定要返回的字符串长度。默认是直到字符串的结尾。

* 正数 - 从 start 参数所在的位置返回
* 负数 - 从字符串末端返回
