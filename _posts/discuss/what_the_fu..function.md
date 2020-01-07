# PHP坑爹函数系列

# Index
- [前言](#前言)
- [strtotime](#strtotime)
- [trim](#trim)
- [getimagesize](#getimagesize)
- [realpath](#realpath)
- [pathinfo](#pathinfo)
- [opendir](#opendir)


# 前言
本系列中指出的问题，绝大部分都是因为对php文档内容不熟悉，或函数说明理解不到位导致的，所以有空多翻翻[php文档](https://www.php.net/manual/zh/index.php)。

# strtotime
> 本函数预期接受一个包含美国英语日期格式的字符串并尝试将其解析为 Unix 时间戳（自 January 1 1970 00:00:00 GMT 起的秒数），其值相对于 now 参数给出的时间，如果没有提供此参数则用系统当前时间。

**禁止给strtotime传入变量作为第一个参数。** 因为你不知道这个变量代表的字符会是什么。千万要注意传入的参数，不可以是任意的字符串，否则可能会导致不确定的输出。之前网上流传的一段代码，通过strtotime来判断日期格式是否正确就是很好的错误示范：

```php
$data='2014-11-11';//这里可以任意格式，因为strtotime函数很强大
$is_date=strtotime($data)?strtotime($data):false;
  
if($is_date===false){
    exit('日期格式非法');
}else{
    echo date('Y-m-d',$is_date);//只要提交的是合法的日期，这里都统一成2014-11-11格式
}
```

# trim
`trim/ltrim/rtrim` 这三个函数，原本是用于去除字符串首尾的空白。但这个函数也支持传入第二个参数来指定要过滤的范围。之前的项目中有出现这样的用法：

```php
$path = '/Resource/video/video.mp4';

$path = ltrim($path, '/Resource');
```

本意是想去除字符串的`/Resource`前缀，但是没想到结果会是 `video/video.mp4`，更没想到会误伤类似 `/Resource/start/video.mp4` 等字符串。因为如果第二个参数是一个字符列表的话，会逐个匹配去除，凡是指定的列表中出现的字符，如果在首位，都会被去除，而不是将`/Resource`作为一个整体去除。

# getimagesize
用于检查指定图片文件的大小，除了可以检查本地文件系统中的文件，还能用于检查网络地址中的文件，这种情况下，就需要考虑因网络延迟导致的性能的问题。之前的项目中遇到过，在某个接口中检查一组网络图片的大小，导致接口返回异常缓慢。

# realpath
realpath用于将目录转化成绝对路径。
> realpath() 扩展所有的符号连接并且处理输入的 path 中的 '/./', '/../' 以及多余的 '/' 并返回规范化后的绝对路径名。返回的路径中没有符号连接，'/./' 或 '/../' 成分。

注意如果指定目录或文件不存在，函数会返回false

# pathinfo
pathinfo有可能出现返回值不正确的情况。这时请注意检查文件名是否包含中文，这可能导致basename为空，解决办法是:
```php
setlocale(LC_ALL, 'en_US.UTF-8');
```

# opendir
在windows下读取映射目录时，有权限的问题。解决方案:
```php
$location = "\\\\ip\web";
$user     = "root";
$pass     = "123456youzu";
$letter   = "Z";
system("net use " . $letter . ": \"" . $location . "\" " . $pass . " /user:" . $user . " /persistent:no>nul 2>&1");
```